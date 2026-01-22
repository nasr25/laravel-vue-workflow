<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ExchangeCalendarService
{
    private $server;
    private $version;
    private $serviceUsername;
    private $servicePassword;

    public function __construct()
    {
        $this->server = config('services.exchange.server');
        $this->version = config('services.exchange.version', 'Exchange2016');
    }

    private function escapeXml($value)
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sendSoapRequest(string $soapAction, string $soapXml, string $username, string $password)
    {
        $url = 'https://' . $this->server;
        
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://schemas.microsoft.com/exchange/services/2006/messages/' . $soapAction . '"'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapXml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: " . $error);
        }

        return ['status' => $httpCode, 'body' => $response];
    }

    private function buildFindItemSoap(string $targetEmail, string $startIso, string $endIso)
    {
        $version = $this->version;
        
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types"
  xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">
  <soap:Header>
    <t:RequestServerVersion Version="{$version}" />
    <t:ExchangeImpersonation>
      <t:ConnectingSID>
        <t:PrimarySmtpAddress>{$this->escapeXml($targetEmail)}</t:PrimarySmtpAddress>
      </t:ConnectingSID>
    </t:ExchangeImpersonation>
  </soap:Header>
  <soap:Body>
    <m:FindItem Traversal="Shallow">
      <m:ItemShape>
        <t:BaseShape>IdOnly</t:BaseShape>
        <t:AdditionalProperties>
          <t:FieldURI FieldURI="item:Subject"/>
          <t:FieldURI FieldURI="calendar:Start"/>
          <t:FieldURI FieldURI="calendar:End"/>
          <t:FieldURI FieldURI="calendar:Location"/>
          <t:FieldURI FieldURI="calendar:Organizer"/>
          <t:FieldURI FieldURI="calendar:IsAllDayEvent"/>
        </t:AdditionalProperties>
      </m:ItemShape>
      <m:CalendarView StartDate="{$this->escapeXml($startIso)}" EndDate="{$this->escapeXml($endIso)}" />
      <m:ParentFolderIds>
        <t:DistinguishedFolderId Id="calendar" />
      </m:ParentFolderIds>
    </m:FindItem>
  </soap:Body>
</soap:Envelope>
XML;
    }

    public function getCalendarEvents(string $serviceUsername, string $servicePassword, string $targetEmail = null, array $options = []): array
    {
        $impersonateUser = $targetEmail ?? $serviceUsername;
        
        Log::info('EWS: Getting calendar events (Direct SOAP)', [
            'service_account' => $serviceUsername,
            'target_user' => $impersonateUser
        ]);

        try {
            $startIso = $options['startDate'] ?? gmdate('Y-m-d\TH:i:s\Z');
            $endIso = $options['endDate'] ?? gmdate('Y-m-d\T23:59:59\Z', strtotime('+30 days'));

            $soap = $this->buildFindItemSoap($impersonateUser, $startIso, $endIso);
            
            Log::info('EWS: Sending SOAP request');
            
            $result = $this->sendSoapRequest('FindItem', $soap, $serviceUsername, $servicePassword);

            if ($result['status'] !== 200) {
                Log::error('EWS: HTTP error', ['status' => $result['status']]);
                throw new \Exception("HTTP error: " . $result['status']);
            }

            // Parse XML response
            $xml = simplexml_load_string($result['body']);
            $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('m', 'http://schemas.microsoft.com/exchange/services/2006/messages');
            $xml->registerXPathNamespace('t', 'http://schemas.microsoft.com/exchange/services/2006/types');

            // Check response class
            $responseMessages = $xml->xpath('//m:FindItemResponseMessage');
            
            if (empty($responseMessages)) {
                Log::error('EWS: No response message found');
                throw new \Exception('Invalid response from Exchange');
            }

            $responseMsg = $responseMessages[0];
            $responseClass = (string)$responseMsg['ResponseClass'];
            
            Log::info('EWS: Response received', [
                'response_class' => $responseClass,
                'response_code' => (string)$responseMsg->m_ResponseCode
            ]);

            if ($responseClass !== 'Success') {
                $errorCode = (string)$responseMsg->m_ResponseCode;
                $errorMsg = (string)$responseMsg->m_MessageText;
                throw new \Exception("EWS Error: {$errorCode} - {$errorMsg}");
            }

            // Extract calendar items
            $items = $xml->xpath('//t:CalendarItem');
            
            Log::info('EWS: Found items', ['count' => count($items)]);

            $events = [];
            
            foreach ($items as $item) {
                $item->registerXPathNamespace('t', 'http://schemas.microsoft.com/exchange/services/2006/types');
                
                $itemId = $item->xpath('.//t:ItemId');
                $subject = $item->xpath('.//t:Subject');
                $start = $item->xpath('.//t:Start');
                $end = $item->xpath('.//t:End');
                $location = $item->xpath('.//t:Location');
                $organizer = $item->xpath('.//t:Organizer/t:Mailbox/t:EmailAddress');
                $isAllDay = $item->xpath('.//t:IsAllDayEvent');

                $events[] = [
                    'id' => $itemId ? (string)$itemId[0]['Id'] : null,
                    'change_key' => $itemId ? (string)$itemId[0]['ChangeKey'] : null,
                    'subject' => $subject ? (string)$subject[0] : '',
                    'start' => $start ? (string)$start[0] : null,
                    'end' => $end ? (string)$end[0] : null,
                    'location' => $location ? (string)$location[0] : '',
                    'organizer' => $organizer ? (string)$organizer[0] : '',
                    'is_all_day' => $isAllDay ? (strtolower((string)$isAllDay[0]) === 'true') : false,
                ];
            }

            Log::info('EWS: Successfully retrieved events', ['count' => count($events)]);

            return $events;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to get calendar events', [
                'service_account' => $serviceUsername,
                'target_user' => $targetEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function buildCreateItemSoap(string $targetEmail, array $eventData)
    {
        $version = $this->version;
        $subject = $this->escapeXml($eventData['subject']);
        $body = $this->escapeXml($eventData['body'] ?? '');
        $start = $this->escapeXml($eventData['start']);
        $end = $this->escapeXml($eventData['end']);
        $location = $this->escapeXml($eventData['location'] ?? '');

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:t="http://schemas.microsoft.com/exchange/services/2006/types"
  xmlns:m="http://schemas.microsoft.com/exchange/services/2006/messages">
  <soap:Header>
    <t:RequestServerVersion Version="{$version}" />
    <t:ExchangeImpersonation>
      <t:ConnectingSID>
        <t:PrimarySmtpAddress>{$this->escapeXml($targetEmail)}</t:PrimarySmtpAddress>
      </t:ConnectingSID>
    </t:ExchangeImpersonation>
  </soap:Header>
  <soap:Body>
    <m:CreateItem SendMeetingInvitations="SendToNone">
      <m:SavedItemFolderId>
        <t:DistinguishedFolderId Id="calendar" />
      </m:SavedItemFolderId>
      <m:Items>
        <t:CalendarItem>
          <t:Subject>{$subject}</t:Subject>
          <t:Body BodyType="HTML">{$body}</t:Body>
          <t:Start>{$start}</t:Start>
          <t:End>{$end}</t:End>
          <t:Location>{$location}</t:Location>
        </t:CalendarItem>
      </m:Items>
    </m:CreateItem>
  </soap:Body>
</soap:Envelope>
XML;
    }

    public function createEvent(string $serviceUsername, string $servicePassword, string $targetEmail, array $eventData): array
    {
        Log::info('EWS: Creating event (Direct SOAP)', ['target' => $targetEmail]);

        try {
            $soap = $this->buildCreateItemSoap($targetEmail, $eventData);
            $result = $this->sendSoapRequest('CreateItem', $soap, $serviceUsername, $servicePassword);

            if ($result['status'] !== 200) {
                throw new \Exception("HTTP error: " . $result['status']);
            }

            $xml = simplexml_load_string($result['body']);
            $xml->registerXPathNamespace('m', 'http://schemas.microsoft.com/exchange/services/2006/messages');
            $xml->registerXPathNamespace('t', 'http://schemas.microsoft.com/exchange/services/2006/types');

            $responseMessages = $xml->xpath('//m:CreateItemResponseMessage');
            
            if (empty($responseMessages)) {
                throw new \Exception('Invalid response from Exchange');
            }

            $responseClass = (string)$responseMessages[0]['ResponseClass'];

            if ($responseClass !== 'Success') {
                throw new \Exception('Failed to create event');
            }

            $itemId = $xml->xpath('//t:ItemId');

            Log::info('EWS: Event created successfully');

            return [
                'id' => $itemId ? (string)$itemId[0]['Id'] : null,
                'change_key' => $itemId ? (string)$itemId[0]['ChangeKey'] : null,
                'subject' => $eventData['subject']
            ];

        } catch (\Exception $e) {
            Log::error('EWS: Failed to create event', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateEvent(string $serviceUsername, string $servicePassword, string $targetEmail, string $itemId, string $changeKey, array $eventData): array
    {
        throw new \Exception('Update not implemented yet');
    }

    public function deleteEvent(string $serviceUsername, string $servicePassword, string $targetEmail, string $itemId, string $changeKey): bool
    {
        throw new \Exception('Delete not implemented yet');
    }
}