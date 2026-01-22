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
    private $client;
    private $server;
    private $version;

    public function __construct()
    {
        $this->server = config('services.exchange.server'); // e.g., 'mail.yourcompany.local'
        $this->version = config('services.exchange.version', Client::VERSION_2016);
    }

    /**
     * Initialize EWS client with user credentials
     */
    private function getClient(string $username, string $password): Client
    {
        // Ensure server URL doesn't have https:// prefix
        $server = $this->server;
        $server = str_replace(['https://', 'http://'], '', $server);

        Log::info('EWS: Attempting to connect', [
            'server' => $server,
            'username' => $username,
            'username_length' => strlen($username),
            'password_length' => strlen($password),
            'version' => $this->version,
            'has_backslash' => strpos($username, '\\') !== false,
            'has_at_sign' => strpos($username, '@') !== false
        ]);

        try {
            $client = new Client(
                $server,
                $username,
                $password,
                $this->version
            );

            // Set authentication method - try NTLM first (most common for on-premises)
            // Other options: CURLAUTH_BASIC, CURLAUTH_DIGEST
            $client->setCurlOptions([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPAUTH => CURLAUTH_NTLM | CURLAUTH_BASIC,  // Try NTLM first, fall back to Basic
            ]);

            Log::info('EWS: Client initialized successfully', [
                'server' => $server,
                'username' => $username
            ]);

            return $client;
        } catch (\Exception $e) {
            Log::error('EWS: Failed to initialize client', [
                'server' => $server,
                'username' => $username,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Cannot connect to Exchange server: {$server}. Error: " . $e->getMessage());
        }
    }

    /**
     * Get calendar events for a user
     * 
     * @param string $username - User's email or domain\username
     * @param string $password - User's password
     * @param array $options - startDate, endDate
     * @return array
     */
    public function getCalendarEvents(string $username, string $password, array $options = []): array
    {
        Log::info('EWS: Starting getCalendarEvents', [
            'username' => $username,
            'options' => $options
        ]);

        try {
            $client = $this->getClient($username, $password);

            $request = new FindItemType();
            $request->Traversal = ItemQueryTraversalType::SHALLOW;
            
            // Shape of items to return
            $request->ItemShape = new ItemResponseShapeType();
            $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

            // Calendar folder
            $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $folder = new DistinguishedFolderIdType();
            $folder->Id = DistinguishedFolderIdNameType::CALENDAR;
            $request->ParentFolderIds->DistinguishedFolderId[] = $folder;

            // Calendar view (date range)
            $request->CalendarView = new CalendarViewType();
            $request->CalendarView->StartDate = $options['startDate'] ?? date('c');
            $request->CalendarView->EndDate = $options['endDate'] ?? date('c', strtotime('+30 days'));

            Log::info('EWS: Sending FindItem request', [
                'start_date' => $request->CalendarView->StartDate,
                'end_date' => $request->CalendarView->EndDate
            ]);

            $response = $client->FindItem($request);

            Log::info('EWS: Received response', [
                'response_class' => $response->ResponseMessages->FindItemResponseMessage[0]->ResponseClass ?? 'unknown'
            ]);

            $events = [];
            
            if ($response->ResponseMessages->FindItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $items = $response->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items->CalendarItem;
                
                if (!is_array($items)) {
                    $items = $items ? [$items] : [];
                }

                Log::info('EWS: Found calendar items', [
                    'count' => count($items)
                ]);

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
                        'required_attendees' => $this->extractAttendees($item->RequiredAttendees ?? null),
                        'optional_attendees' => $this->extractAttendees($item->OptionalAttendees ?? null)
                    ];
                }

                Log::info('EWS: Successfully retrieved calendar events', [
                    'count' => count($events)
                ]);
            } else {
                $errorCode = $response->ResponseMessages->FindItemResponseMessage[0]->ResponseCode ?? 'Unknown';
                $errorMessage = $response->ResponseMessages->FindItemResponseMessage[0]->MessageText ?? 'Unknown error';
                
                Log::error('EWS: FindItem request failed', [
                    'response_class' => $response->ResponseMessages->FindItemResponseMessage[0]->ResponseClass,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage
                ]);
            }

            return $events;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to get calendar events', [
                'user' => $username,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to retrieve calendar events: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific event
     */
    public function getEvent(string $username, string $password, string $itemId, string $changeKey): ?array
    {
        try {
            $client = $this->getClient($username, $password);

            $request = new GetItemType();
            $request->ItemShape = new ItemResponseShapeType();
            $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

            $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
            $item = new ItemIdType();
            $item->Id = $itemId;
            $item->ChangeKey = $changeKey;
            $request->ItemIds->ItemId[] = $item;

            $response = $client->GetItem($request);

            if ($response->ResponseMessages->GetItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $item = $response->ResponseMessages->GetItemResponseMessage[0]->Items->CalendarItem[0];
                
                return [
                    'id' => $item->ItemId->Id,
                    'change_key' => $item->ItemId->ChangeKey,
                    'subject' => $item->Subject ?? '',
                    'start' => $item->Start ?? null,
                    'end' => $item->End ?? null,
                    'location' => $item->Location ?? '',
                    'organizer' => $item->Organizer->Mailbox->EmailAddress ?? '',
                    'body' => $item->Body->_ ?? '',
                    'is_all_day' => $item->IsAllDayEvent ?? false
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to get event', [
                'user' => $username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to retrieve event: ' . $e->getMessage());
        }
    }

    /**
     * Create a calendar event
     */
    public function createEvent(string $username, string $password, array $eventData): array
    {
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
            $event->Start = $eventData['start'];
            $event->End = $eventData['end'];
            
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
                
                return [
                    'id' => $item->ItemId->Id,
                    'change_key' => $item->ItemId->ChangeKey,
                    'subject' => $eventData['subject']
                ];
            }

            throw new \Exception('Failed to create event');

        } catch (\Exception $e) {
            Log::error('EWS: Failed to create event', [
                'user' => $username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create calendar event: ' . $e->getMessage());
        }
    }

    /**
     * Update a calendar event
     */
    public function updateEvent(string $username, string $password, string $itemId, string $changeKey, array $eventData): array
    {
        try {
            $client = $this->getClient($username, $password);

            $request = new UpdateItemType();
            $request->SendMeetingInvitationsOrCancellations = CalendarItemUpdateOperationType::SEND_TO_NONE;
            $request->MessageDisposition = \jamesiarmes\PhpEws\Enumeration\MessageDispositionType::SAVE_ONLY;
            $request->ConflictResolution = \jamesiarmes\PhpEws\Enumeration\ConflictResolutionType::ALWAYS_OVERWRITE;

            $change = new \jamesiarmes\PhpEws\Type\ItemChangeType();
            $change->ItemId = new ItemIdType();
            $change->ItemId->Id = $itemId;
            $change->ItemId->ChangeKey = $changeKey;

            $change->Updates = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfItemChangeDescriptionsType();

            // Update subject
            if (isset($eventData['subject'])) {
                $field = new \jamesiarmes\PhpEws\Type\SetItemFieldType();
                $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = 'item:Subject';
                $field->CalendarItem = new CalendarItemType();
                $field->CalendarItem->Subject = $eventData['subject'];
                $change->Updates->SetItemField[] = $field;
            }

            // Update location
            if (isset($eventData['location'])) {
                $field = new \jamesiarmes\PhpEws\Type\SetItemFieldType();
                $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = 'calendar:Location';
                $field->CalendarItem = new CalendarItemType();
                $field->CalendarItem->Location = $eventData['location'];
                $change->Updates->SetItemField[] = $field;
            }

            // Update start time
            if (isset($eventData['start'])) {
                $field = new \jamesiarmes\PhpEws\Type\SetItemFieldType();
                $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = 'calendar:Start';
                $field->CalendarItem = new CalendarItemType();
                $field->CalendarItem->Start = $eventData['start'];
                $change->Updates->SetItemField[] = $field;
            }

            // Update end time
            if (isset($eventData['end'])) {
                $field = new \jamesiarmes\PhpEws\Type\SetItemFieldType();
                $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                $field->FieldURI->FieldURI = 'calendar:End';
                $field->CalendarItem = new CalendarItemType();
                $field->CalendarItem->End = $eventData['end'];
                $change->Updates->SetItemField[] = $field;
            }

            $request->ItemChanges = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfItemChangesType();
            $request->ItemChanges->ItemChange[] = $change;

            $response = $client->UpdateItem($request);

            if ($response->ResponseMessages->UpdateItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $item = $response->ResponseMessages->UpdateItemResponseMessage[0]->Items->CalendarItem[0];
                
                return [
                    'id' => $item->ItemId->Id,
                    'change_key' => $item->ItemId->ChangeKey
                ];
            }

            throw new \Exception('Failed to update event');

        } catch (\Exception $e) {
            Log::error('EWS: Failed to update event', [
                'user' => $username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to update calendar event: ' . $e->getMessage());
        }
    }

    /**
     * Delete a calendar event
     */
    public function deleteEvent(string $username, string $password, string $itemId, string $changeKey): bool
    {
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

            return $response->ResponseMessages->DeleteItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to delete event', [
                'user' => $username,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to delete calendar event: ' . $e->getMessage());
        }
    }

    /**
     * Extract attendees from EWS response
     */
    private function extractAttendees($attendees): array
    {
        if (!$attendees || !isset($attendees->Attendee)) {
            return [];
        }

        $list = is_array($attendees->Attendee) ? $attendees->Attendee : [$attendees->Attendee];
        
        return array_map(function ($attendee) {
            return [
                'name' => $attendee->Mailbox->Name ?? '',
                'email' => $attendee->Mailbox->EmailAddress ?? ''
            ];
        }, $list);
    }
}