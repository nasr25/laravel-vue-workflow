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
        $client = new Client(
            $this->server,
            $username,
            $password,
            $this->version
        );

        // For self-signed certificates (common in private networks)
        $client->setCurlOptions([
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        return $client;
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

            $response = $client->FindItem($request);

            $events = [];
            
            if ($response->ResponseMessages->FindItemResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                $items = $response->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items->CalendarItem;
                
                if (!is_array($items)) {
                    $items = $items ? [$items] : [];
                }

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
            }

            return $events;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to get calendar events', [
                'user' => $username,
                'error' => $e->getMessage()
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