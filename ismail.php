<?php

namespace App\Services;

use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Request\FindItemType;
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
     * Initialize EWS client with service account and impersonation
     * 
     * @param string $serviceUsername - Service account username
     * @param string $servicePassword - Service account password
     * @param string $targetEmail - Target user's email to impersonate (optional)
     */
    private function getClient(string $serviceUsername, string $servicePassword, string $targetEmail = null): Client
    {
        $server = str_replace(['https://', 'http://'], '', $this->server);

        Log::info('EWS: Attempting to connect', [
            'server' => $server,
            'service_account' => $serviceUsername,
            'target_user' => $targetEmail ?? 'N/A',
            'using_impersonation' => $targetEmail !== null
        ]);

        try {
            $client = new Client($server, $serviceUsername, $servicePassword, $this->version);

            $client->setCurlOptions([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            ]);

            // If target email is provided, use impersonation
            if ($targetEmail) {
                Log::info('EWS: Setting up impersonation', ['target' => $targetEmail]);
                $client->setImpersonation(
                    \jamesiarmes\PhpEws\Enumeration\ConnectingSIDType::SMTP_ADDRESS,
                    $targetEmail
                );
            }

            Log::info('EWS: Client initialized successfully');
            return $client;

        } catch (\Exception $e) {
            Log::error('EWS: Failed to initialize client', [
                'server' => $server,
                'service_account' => $serviceUsername,
                'target_user' => $targetEmail,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Cannot connect to Exchange server. Error: " . $e->getMessage());
        }
    }

    /**
     * Get calendar events for a user using service account
     * 
     * @param string $serviceUsername - Service account username
     * @param string $servicePassword - Service account password  
     * @param string $targetEmail - Target user's email
     * @param array $options - startDate, endDate
     */
    public function getCalendarEvents(string $serviceUsername, string $servicePassword, string $targetEmail = null, array $options = []): array
    {
        Log::info('EWS: Getting calendar events', [
            'service_account' => $serviceUsername,
            'target_user' => $targetEmail ?? $serviceUsername
        ]);

        try {
            // If targetEmail is not provided, use serviceUsername as the target
            $impersonateUser = $targetEmail ?? $serviceUsername;
            $client = $this->getClient($serviceUsername, $servicePassword, $targetEmail ? $impersonateUser : null);

            // Test calendar folder access first
            $folderRequest = new \jamesiarmes\PhpEws\Request\GetFolderType();
            $folderRequest->FolderShape = new \jamesiarmes\PhpEws\Type\FolderResponseShapeType();
            $folderRequest->FolderShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

            $folderRequest->FolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $folder = new DistinguishedFolderIdType();
            $folder->Id = DistinguishedFolderIdNameType::CALENDAR;
            
            // If impersonating, specify the mailbox
            if ($targetEmail) {
                $folder->Mailbox = new \jamesiarmes\PhpEws\Type\EmailAddressType();
                $folder->Mailbox->EmailAddress = $targetEmail;
            }
            
            $folderRequest->FolderIds->DistinguishedFolderId[] = $folder;

            Log::info('EWS: Testing calendar access');
            
            try {
                $folderResponse = $client->GetFolder($folderRequest);
                
                if ($folderResponse->ResponseMessages->GetFolderResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                    Log::info('EWS: Calendar folder accessible');
                } else {
                    $errorCode = $folderResponse->ResponseMessages->GetFolderResponseMessage[0]->ResponseCode ?? 'Unknown';
                    $errorMsg = $folderResponse->ResponseMessages->GetFolderResponseMessage[0]->MessageText ?? 'Unknown';
                    Log::error('EWS: Calendar folder not accessible', [
                        'code' => $errorCode,
                        'message' => $errorMsg
                    ]);
                    throw new \Exception("Cannot access calendar: {$errorCode} - {$errorMsg}");
                }
            } catch (\SoapFault $e) {
                Log::error('EWS: SOAP Fault accessing folder', [
                    'fault' => $e->faultstring ?? $e->getMessage()
                ]);
                throw new \Exception('Calendar access denied: ' . ($e->faultstring ?? $e->getMessage()));
            }

            // Now get calendar items
            $findRequest = new FindItemType();
            $findRequest->Traversal = ItemQueryTraversalType::SHALLOW;
            
            $findRequest->ItemShape = new ItemResponseShapeType();
            $findRequest->ItemShape->BaseShape = DefaultShapeNamesType::ID_ONLY;

            $findRequest->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $calFolder = new DistinguishedFolderIdType();
            $calFolder->Id = DistinguishedFolderIdNameType::CALENDAR;
            
            // Specify mailbox if impersonating
            if ($targetEmail) {
                $calFolder->Mailbox = new \jamesiarmes\PhpEws\Type\EmailAddressType();
                $calFolder->Mailbox->EmailAddress = $targetEmail;
            }
            
            $findRequest->ParentFolderIds->DistinguishedFolderId[] = $calFolder;

            // Set pagination
            $findRequest->IndexedPageItemView = new \jamesiarmes\PhpEws\Type\IndexedPageViewType();
            $findRequest->IndexedPageItemView->MaxEntriesReturned = 100;
            $findRequest->IndexedPageItemView->Offset = 0;
            $findRequest->IndexedPageItemView->BasePoint = \jamesiarmes\PhpEws\Enumeration\IndexBasePointType::BEGINNING;
            
            Log::info('EWS: Finding calendar items');
            
            try {
                $response = $client->FindItem($findRequest);
            } catch (\SoapFault $e) {
                Log::error('EWS: SOAP Fault finding items', [
                    'fault_code' => $e->faultcode ?? 'N/A',
                    'fault_string' => $e->faultstring ?? 'N/A'
                ]);
                throw new \Exception("Error finding items: " . ($e->faultstring ?? $e->getMessage()));
            }

            $events = [];
            
            if (!isset($response->ResponseMessages->FindItemResponseMessage[0])) {
                throw new \Exception('Invalid response structure from Exchange');
            }
            
            $responseMessage = $response->ResponseMessages->FindItemResponseMessage[0];
            
            Log::info('EWS: FindItem response', [
                'response_class' => $responseMessage->ResponseClass ?? 'N/A',
                'response_code' => $responseMessage->ResponseCode ?? 'N/A'
            ]);
            
            if ($responseMessage->ResponseClass === ResponseClassType::SUCCESS) {
                $itemIds = [];
                $items = $responseMessage->RootFolder->Items->CalendarItem ?? [];
                
                if (!is_array($items)) {
                    $items = $items ? [$items] : [];
                }

                Log::info('EWS: Found item IDs', ['count' => count($items)]);
                
                foreach ($items as $item) {
                    $itemIds[] = $item->ItemId;
                }
                
                // Get full details
                if (count($itemIds) > 0) {
                    $getRequest = new \jamesiarmes\PhpEws\Request\GetItemType();
                    $getRequest->ItemShape = new ItemResponseShapeType();
                    $getRequest->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;
                    
                    $getRequest->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
                    foreach ($itemIds as $itemId) {
                        $getRequest->ItemIds->ItemId[] = $itemId;
                    }
                    
                    Log::info('EWS: Getting full item details');
                    $getResponse = $client->GetItem($getRequest);
                    
                    $getItems = $getResponse->ResponseMessages->GetItemResponseMessage ?? [];
                    if (!is_array($getItems)) {
                        $getItems = [$getItems];
                    }
                    
                    foreach ($getItems as $getMessage) {
                        if ($getMessage->ResponseClass === ResponseClassType::SUCCESS) {
                            $calItems = $getMessage->Items->CalendarItem ?? [];
                            if (!is_array($calItems)) {
                                $calItems = $calItems ? [$calItems] : [];
                            }
                            
                            foreach ($calItems as $item) {
                                // Apply date filtering
                                $startFilter = isset($options['startDate']) ? strtotime($options['startDate']) : null;
                                $endFilter = isset($options['endDate']) ? strtotime($options['endDate']) : null;
                                
                                if ($startFilter || $endFilter) {
                                    $itemStart = isset($item->Start) ? strtotime($item->Start) : null;
                                    
                                    if ($startFilter && $itemStart && $itemStart < $startFilter) {
                                        continue;
                                    }
                                    
                                    if ($endFilter && $itemStart && $itemStart > $endFilter) {
                                        continue;
                                    }
                                }
                                
                                $events[] = [
                                    'id' => $item->ItemId->Id,
                                    'change_key' => $item->ItemId->ChangeKey,
                                    'subject' => $item->Subject ?? '',
                                    'start' => $item->Start ?? null,
                                    'end' => $item->End ?? null,
                                    'location' => $item->Location ?? '',
                                    'organizer' => $item->Organizer->Mailbox->EmailAddress ?? '',
                                    'is_all_day' => $item->IsAllDayEvent ?? false,
                                ];
                            }
                        }
                    }
                }

                Log::info('EWS: Successfully retrieved events', ['count' => count($events)]);
            } else {
                $errorCode = $responseMessage->ResponseCode ?? 'Unknown';
                $errorMsg = $responseMessage->MessageText ?? 'Unknown';
                
                Log::error('EWS: Request failed', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMsg
                ]);
                
                throw new \Exception("EWS Error: {$errorCode} - {$errorMsg}");
            }

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
    {
        Log::info('EWS: Getting calendar events', ['username' => $username]);

        try {
            $client = $this->getClient($username, $password);

            // Use GetFolder to access calendar
            $request = new \jamesiarmes\PhpEws\Request\GetFolderType();
            $request->FolderShape = new \jamesiarmes\PhpEws\Type\FolderResponseShapeType();
            $request->FolderShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

            $request->FolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $folder = new DistinguishedFolderIdType();
            $folder->Id = DistinguishedFolderIdNameType::CALENDAR;
            $request->FolderIds->DistinguishedFolderId[] = $folder;

            Log::info('EWS: Testing calendar access with GetFolder');
            
            try {
                $folderResponse = $client->GetFolder($request);
                
                if ($folderResponse->ResponseMessages->GetFolderResponseMessage[0]->ResponseClass === ResponseClassType::SUCCESS) {
                    Log::info('EWS: Calendar folder accessible');
                } else {
                    Log::error('EWS: Calendar folder not accessible', [
                        'code' => $folderResponse->ResponseMessages->GetFolderResponseMessage[0]->ResponseCode ?? 'N/A',
                        'message' => $folderResponse->ResponseMessages->GetFolderResponseMessage[0]->MessageText ?? 'N/A'
                    ]);
                    throw new \Exception('Cannot access calendar folder');
                }
            } catch (\Exception $e) {
                Log::error('EWS: Failed to access calendar folder', ['error' => $e->getMessage()]);
                throw new \Exception('Calendar access denied or calendar does not exist');
            }

            // Now try FindItem with minimal settings
            $findRequest = new FindItemType();
            $findRequest->Traversal = ItemQueryTraversalType::SHALLOW;
            
            // Use absolute minimum item shape
            $findRequest->ItemShape = new ItemResponseShapeType();
            $findRequest->ItemShape->BaseShape = DefaultShapeNamesType::ID_ONLY;

            $findRequest->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $calFolder = new DistinguishedFolderIdType();
            $calFolder->Id = DistinguishedFolderIdNameType::CALENDAR;
            $findRequest->ParentFolderIds->DistinguishedFolderId[] = $calFolder;

            // Set max items to avoid overload
            $findRequest->IndexedPageItemView = new \jamesiarmes\PhpEws\Type\IndexedPageViewType();
            $findRequest->IndexedPageItemView->MaxEntriesReturned = 100;
            $findRequest->IndexedPageItemView->Offset = 0;
            $findRequest->IndexedPageItemView->BasePoint = \jamesiarmes\PhpEws\Enumeration\IndexBasePointType::BEGINNING;
            
            Log::info('EWS: Sending FindItem request with pagination');
            
            try {
                $response = $client->FindItem($findRequest);
            } catch (\SoapFault $e) {
                Log::error('EWS: SOAP Fault', [
                    'fault_code' => $e->faultcode ?? 'N/A',
                    'fault_string' => $e->faultstring ?? 'N/A',
                    'detail' => isset($e->detail) ? json_encode($e->detail) : 'N/A'
                ]);
                throw new \Exception("SOAP Error: " . ($e->faultstring ?? $e->getMessage()));
            }

            $events = [];
            
            if (!isset($response->ResponseMessages->FindItemResponseMessage[0])) {
                throw new \Exception('Invalid response structure from Exchange');
            }
            
            $responseMessage = $response->ResponseMessages->FindItemResponseMessage[0];
            
            Log::info('EWS: Response received', [
                'response_class' => $responseMessage->ResponseClass ?? 'N/A',
                'response_code' => $responseMessage->ResponseCode ?? 'N/A',
                'message_text' => $responseMessage->MessageText ?? 'N/A'
            ]);
            
            if ($responseMessage->ResponseClass === ResponseClassType::SUCCESS) {
                // Get item IDs first
                $itemIds = [];
                $items = $responseMessage->RootFolder->Items->CalendarItem ?? [];
                
                if (!is_array($items)) {
                    $items = $items ? [$items] : [];
                }

                Log::info('EWS: Found item IDs', ['count' => count($items)]);
                
                foreach ($items as $item) {
                    $itemIds[] = $item->ItemId;
                }
                
                // Now get full details for each item
                if (count($itemIds) > 0) {
                    $getRequest = new \jamesiarmes\PhpEws\Request\GetItemType();
                    $getRequest->ItemShape = new ItemResponseShapeType();
                    $getRequest->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;
                    
                    $getRequest->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
                    foreach ($itemIds as $itemId) {
                        $getRequest->ItemIds->ItemId[] = $itemId;
                    }
                    
                    Log::info('EWS: Getting full item details');
                    $getResponse = $client->GetItem($getRequest);
                    
                    $getItems = $getResponse->ResponseMessages->GetItemResponseMessage ?? [];
                    if (!is_array($getItems)) {
                        $getItems = [$getItems];
                    }
                    
                    foreach ($getItems as $getMessage) {
                        if ($getMessage->ResponseClass === ResponseClassType::SUCCESS) {
                            $calItems = $getMessage->Items->CalendarItem ?? [];
                            if (!is_array($calItems)) {
                                $calItems = $calItems ? [$calItems] : [];
                            }
                            
                            foreach ($calItems as $item) {
                                // Apply date filtering if specified
                                $startFilter = isset($options['startDate']) ? strtotime($options['startDate']) : null;
                                $endFilter = isset($options['endDate']) ? strtotime($options['endDate']) : null;
                                
                                if ($startFilter || $endFilter) {
                                    $itemStart = isset($item->Start) ? strtotime($item->Start) : null;
                                    
                                    if ($startFilter && $itemStart && $itemStart < $startFilter) {
                                        continue;
                                    }
                                    
                                    if ($endFilter && $itemStart && $itemStart > $endFilter) {
                                        continue;
                                    }
                                }
                                
                                $events[] = [
                                    'id' => $item->ItemId->Id,
                                    'change_key' => $item->ItemId->ChangeKey,
                                    'subject' => $item->Subject ?? '',
                                    'start' => $item->Start ?? null,
                                    'end' => $item->End ?? null,
                                    'location' => $item->Location ?? '',
                                    'organizer' => $item->Organizer->Mailbox->EmailAddress ?? '',
                                    'is_all_day' => $item->IsAllDayEvent ?? false,
                                ];
                            }
                        }
                    }
                }

                Log::info('EWS: Successfully retrieved events', ['count' => count($events)]);
            } else {
                $errorCode = $responseMessage->ResponseCode ?? 'Unknown';
                $errorMsg = $responseMessage->MessageText ?? 'Unknown';
                
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
            
            $startDate = $eventData['start'];
            $endDate = $eventData['end'];
            
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
                        $value = preg_replace('/([+-]\d{2}:\d{2}|Z)$/', '', $value) . 'Z';
                        $field->CalendarItem->Start = $value;
                        break;
                    case 'end':
                        $field->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                        $field->FieldURI->FieldURI = 'calendar:End';
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