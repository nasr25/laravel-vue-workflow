<?php

namespace App\Services;

use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Request\FindItemType;
use jamesiarmes\PhpEws\Request\GetItemType;
use jamesiarmes\PhpEws\Request\CreateItemType;
use jamesiarmes\PhpEws\Request\UpdateItemType;
use jamesiarmes\PhpEws\Request\DeleteItemType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use jamesiarmes\PhpEws\Enumeration\CalendarItemCreateOrDeleteOperationType;
use jamesiarmes\PhpEws\Enumeration\CalendarItemUpdateOperationType;
use jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use jamesiarmes\PhpEws\Enumeration\DisposalType;
use jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use jamesiarmes\PhpEws\Enumeration\ItemQueryTraversalType;
use jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Type\CalendarItemType;
use jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use jamesiarmes\PhpEws\Type\ItemIdType;
use jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use jamesiarmes\PhpEws\Type\CalendarViewType;
use Illuminate\Support\Facades\Log;

class ExchangeCalendarService
{
    private $server;
    private $version;

    public function __construct()
    {
        $this->server = config('services.exchange.server');
        $this->version = config('services.exchange.version', Client::VERSION_2016);
    }

    /**
     * Initialize EWS client with user credentials
     */
    private function getClient(string $username, string $password): Client
    {
        // Ensure server URL doesn't have https:// prefix
        $server = str_replace(['https://', 'http://'], '', $this->server);

        Log::info('EWS: Attempting to connect', [
            'server' => $server,
            'username' => $username,
            'is_email' => filter_var($username, FILTER_VALIDATE_EMAIL) !== false
        ]);

        try {
            $client = new Client($server, $username, $password, $this->version);

            // Use Basic Auth (since browser login works)
            $client->setCurlOptions([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            ]);

            Log::info('EWS: Client initialized successfully');
            return $client;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to initialize client', [
                'server' => $server,
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Cannot connect to Exchange server. Error: " . $e->getMessage());
        }
    }

    /**
     * Get calendar events for a user
     */
    public function getCalendarEvents(string $username, string $password, array $options = []): array
    {
        Log::info('EWS: Getting calendar events', ['username' => $username]);

        try {
            $client = $this->getClient($username, $password);

            $request = new FindItemType();
            $request->Traversal = ItemQueryTraversalType::SHALLOW;
            
            $request->ItemShape = new ItemResponseShapeType();
            $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

            $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $folder = new DistinguishedFolderIdType();
            $folder->Id = DistinguishedFolderIdNameType::CALENDAR;
            $request->ParentFolderIds->DistinguishedFolderId[] = $folder;

            // Calendar view with proper ISO 8601 format
            $request->CalendarView = new CalendarViewType();
            
            // Ensure dates are in proper format
            $startDate = $options['startDate'] ?? date('Y-m-d\TH:i:s\Z');
            $endDate = $options['endDate'] ?? date('Y-m-d\TH:i:s\Z', strtotime('+30 days'));
            
            // Remove timezone if present and add Z (UTC)
            $startDate = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $startDate) . 'Z';
            $endDate = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $endDate) . 'Z';
            
            $request->CalendarView->StartDate = $startDate;
            $request->CalendarView->EndDate = $endDate;
            
            Log::info('EWS: Calendar view dates', [
                'start' => $startDate,
                'end' => $endDate
            ]);

            Log::info('EWS: Sending FindItem request');
            $response = $client->FindItem($request);

            $events = [];
            
            if ($response->ResponseMessages->FindItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $items = $response->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items->CalendarItem ?? [];
                
                if (!is_array($items)) {
                    $items = $items ? [$items] : [];
                }

                Log::info('EWS: Found items', ['count' => count($items)]);

                foreach ($items as $item) {
                    $events[] = [
                        'id' => $item->ItemId->Id,
                        'change_key' => $item->ItemId->ChangeKey,
                        'subject' => $item->Subject ?? '',
                        'start' => $item->Start ?? null,
                        'end' => $item->End ?? null,
                        'location' => $item->Location ?? '',
                        'organizer' => $item->Organizer->Mailbox->EmailAddress ?? '',
                        'body' => $item->Body->_ ?? '',
                        'is_all_day' => $item->IsAllDayEvent ?? false,
                    ];
                }

                Log::info('EWS: Successfully retrieved events', ['count' => count($events)]);
            } else {
                $errorCode = $response->ResponseMessages->FindItemResponseMessage[0]->ResponseCode ?? 'Unknown';
                $errorMsg = $response->ResponseMessages->FindItemResponseMessage[0]->MessageText ?? 'Unknown';
                
                Log::error('EWS: Request failed', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMsg
                ]);
                
                throw new \Exception("EWS Error: {$errorCode} - {$errorMsg}");
            }

            return $events;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to get calendar events', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a calendar event
     */
    public function createEvent(string $username, string $password, array $eventData): array
    {
        Log::info('EWS: Creating event', ['username' => $username, 'subject' => $eventData['subject'] ?? '']);

        try {
            $client = $this->getClient($username, $password);

            $request = new CreateItemType();
            $request->SendMeetingInvitations = CalendarItemCreateOrDeleteOperationType::SEND_TO_NONE;
            $request->SavedItemFolderId = new NonEmptyArrayOfBaseFolderIdsType();
            
            $folder = new DistinguishedFolderIdType();
            $folder->Id = DistinguishedFolderIdNameType::CALENDAR;
            $request->SavedItemFolderId->DistinguishedFolderId[] = $folder;

            $event = new CalendarItemType();
            $event->Subject = $eventData['subject'];
            
            // Ensure dates are in proper format
            $startDate = $eventData['start'];
            $endDate = $eventData['end'];
            
            // Remove timezone if present and add Z (UTC)
            $startDate = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $startDate) . 'Z';
            $endDate = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $endDate) . 'Z';
            
            $event->Start = $startDate;
            $event->End = $endDate;
            
            if (isset($eventData['location'])) {
                $event->Location = $eventData['location'];
            }

            if (isset($eventData['body'])) {
                $event->Body = new \jamesiarmes\PhpEws\Type\BodyType();
                $event->Body->BodyType = \jamesiarmes\PhpEws\Enumeration\BodyTypeType::HTML;
                $event->Body->_ = $eventData['body'];
            }

            if (isset($eventData['is_all_day'])) {
                $event->IsAllDayEvent = $eventData['is_all_day'];
            }

            $request->Items = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType();
            $request->Items->CalendarItem[] = $event;

            $response = $client->CreateItem($request);

            if ($response->ResponseMessages->CreateItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $item = $response->ResponseMessages->CreateItemResponseMessage[0]->Items->CalendarItem[0];
                
                Log::info('EWS: Event created successfully');
                
                return [
                    'id' => $item->ItemId->Id,
                    'change_key' => $item->ItemId->ChangeKey,
                    'subject' => $eventData['subject']
                ];
            }

            throw new \Exception('Failed to create event');

        } catch (\Exception $e) {
            Log::error('EWS: Failed to create event', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update a calendar event
     */
    public function updateEvent(string $username, string $password, string $itemId, string $changeKey, array $eventData): array
    {
        Log::info('EWS: Updating event', ['username' => $username, 'item_id' => substr($itemId, 0, 20)]);

        try {
            $client = $this->getClient($username, $password);

            $request = new UpdateItemType();
            $request->SendMeetingInvitationsOrCancellations = CalendarItemUpdateOperationType::SEND_TO_NONE;
            $request->ConflictResolution = \jamesiarmes\PhpEws\Enumeration\ConflictResolutionType::ALWAYS_OVERWRITE;

            $change = new \jamesiarmes\PhpEws\Type\ItemChangeType();
            $change->ItemId = new ItemIdType();
            $change->ItemId->Id = $itemId;
            $change->ItemId->ChangeKey = $changeKey;

            $change->Updates = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfItemChangeDescriptionsType();

            foreach ($eventData as $key => $value) {
                $field = new \jamesiarmes\PhpEws\Type\SetItemFieldType();
                $field->CalendarItem = new CalendarItemType();
                
                switch ($key) {
                    case 'subject':
                        $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                        $field->FieldURI->FieldURI = 'item:Subject';
                        $field->CalendarItem->Subject = $value;
                        break;
                    case 'location':
                        $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                        $field->FieldURI->FieldURI = 'calendar:Location';
                        $field->CalendarItem->Location = $value;
                        break;
                    case 'start':
                        $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                        $field->FieldURI->FieldURI = 'calendar:Start';
                        // Fix date format
                        $value = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $value) . 'Z';
                        $field->CalendarItem->Start = $value;
                        break;
                    case 'end':
                        $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                        $field->FieldURI->FieldURI = 'calendar:End';
                        // Fix date format
                        $value = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $value) . 'Z';
                        $field->CalendarItem->End = $value;
                        break;
                }
                
                $change->Updates->SetItemField[] = $field;
            }

            $request->ItemChanges = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfItemChangesType();
            $request->ItemChanges->ItemChange[] = $change;

            $response = $client->UpdateItem($request);

            if ($response->ResponseMessages->UpdateItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $item = $response->ResponseMessages->UpdateItemResponseMessage[0]->Items->CalendarItem[0];
                
                Log::info('EWS: Event updated successfully');
                
                return [
                    'id' => $item->ItemId->Id,
                    'change_key' => $item->ItemId->ChangeKey
                ];
            }

            throw new \Exception('Failed to update event');

        } catch (\Exception $e) {
            Log::error('EWS: Failed to update event', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a calendar event
     */
    public function deleteEvent(string $username, string $password, string $itemId, string $changeKey): bool
    {
        Log::info('EWS: Deleting event', ['username' => $username, 'item_id' => substr($itemId, 0, 20)]);

        try {
            $client = $this->getClient($username, $password);

            $request = new DeleteItemType();
            $request->DeleteType = DisposalType::HARD_DELETE;
            $request->SendMeetingCancellations = CalendarItemCreateOrDeleteOperationType::SEND_TO_NONE;

            $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
            $item = new ItemIdType();
            $item->Id = $itemId;
            $item->ChangeKey = $changeKey;
            $request->ItemIds->ItemId[] = $item;

            $response = $client->DeleteItem($request);

            $success = $response->ResponseMessages->DeleteItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS;
            
            if ($success) {
                Log::info('EWS: Event deleted successfully');
            }
            
            return $success;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to delete event', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}