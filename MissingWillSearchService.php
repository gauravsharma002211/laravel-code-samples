<?php

namespace App\Services;

use App\Enums\EmailResponseTemplateCode;
use App\Exceptions\InvalidDataException;
use Exception;
use App\Models\Invoice;
use App\Models\MissingWillSearch;
use App\Models\MissingWillSearchInvoice;
use App\Services\PaymentService;
use App\Services\CommonService;
use App\Mail\WillSearchEmail;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use App\Jobs\GenerateMissingWillSearchesReport;
use App\Models\State;

class MissingWillSearchService
{
    protected $price, $paymentService, $commonService, $mailArray;

    public function __construct()
    {
        $this->price = Config::get('payment.PRICE');
        $this->mailArray = [];
        $this->paymentService = new PaymentService();
        $this->commonService = new CommonService();
    }

    public function processWillSearch($request)
    {
        $pinInfo = $request['pinInfo'];
        $searcherInfo = $request['searcherInfo'];
        $deceasedInfo = $request['deceasedInfo'];
        $billingDetails = isset($request['billingDetails']) ? $request['billingDetails'] : [];
        $cardDetails = isset($request['cardDetails']) ? $request['cardDetails'] : [];

        //When user has entered searching pin
        if ($pinInfo['type'] == 'yes' && !empty($pinInfo['searching_pin'])) {
            $mwsInvoice = MissingWillSearchInvoice::where('search_pin', $pinInfo['searching_pin'])->first();
            if (isset($mwsInvoice)) {
                if ($mwsInvoice->transaction_count >= 0 && $mwsInvoice->transaction_count < 3) {
                    return $this->insertAndFindMissingWillSearchDetails($searcherInfo, $deceasedInfo, $mwsInvoice);
                }
                throw new InvalidDataException('No more searches available. You have already utilized all 3 searches for this Search PIN.');
            }
            throw new InvalidDataException("Search PIN is invalid or unable to find payment detail associated with the Search PIN. Please try again!");
        } else if ($pinInfo['type'] == 'no' && empty($pinInfo['searching_pin'])) {
            $mwsInvoice = $this->generateMissingWillInvoice($billingDetails, $cardDetails);
            if (isset($mwsInvoice)) {
                $this->sendMissingWillSearchInvoiceEmail($mwsInvoice, $searcherInfo, $deceasedInfo, $billingDetails);
                return $this->insertAndFindMissingWillSearchDetails($searcherInfo, $deceasedInfo, $mwsInvoice);
            }
            throw new InvalidDataException('Oops!! Something went wrong while inserting the record.');
        }
    }

    protected function generateMissingWillInvoice($billingDetails, $cardDetails)
    {
        // Generate Invoice Number
        $invoiceNumber = $this->commonService->generateUniqueCode('MWS');

        //process payment
        $processedPaymentResponse = $this->paymentService->processPayment($invoiceNumber, $cardDetails, $billingDetails);
        // Check payment status If not success then It'll return 
        if ($processedPaymentResponse['status']) {
            // Save card details
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'transaction_id' => $processedPaymentResponse['transactionID'],
                'price' => $this->price,
                'payment_type' => Config::get('constants.PAYMENT_TYPE.CREDITCARD_DATABASE_VALUE'),
                'card_number' => substr($cardDetails['card_number'], -4),
                'payment_status' => Config::get('constants.PAYMENT_STATUS.APPROVED'),
                'payment_date' => now(),
            ]);
            $invoice->billingInfo()->create($billingDetails);

            // Generate and check search pin
            $generateRandomSearchPin = $this->randomPin();
            $mwsInvoice = MissingWillSearchInvoice::create([
                'search_pin' => $generateRandomSearchPin,
                'invoice_id' => $invoice->id,
                'transaction_count' => 0,
            ]);
            return $mwsInvoice;
        }
        throw new InvalidDataException($processedPaymentResponse['message']);
    }

    protected function randomPin()
    {
        do {
            $randomPin = $this->commonService->randomPin(8);
        } while (MissingWillSearchInvoice::where('search_pin', $randomPin)->exists());

        return $randomPin;
    }

    protected function insertAndFindMissingWillSearchDetails($searcherInfo, $deceasedInfo, $mwsInvoice)
    {
        $mwsDetails = array_merge($searcherInfo, $deceasedInfo, ['mwsi_id' => $mwsInvoice->id]);
        $missingWillSearch = MissingWillSearch::create($mwsDetails);
        $responseTemplates = CommonService::getResponseTemplates([
            EmailResponseTemplateCode::FIND_A_MISSING_WILL_FOUND_RESPONSE,
            EmailResponseTemplateCode::FIND_A_MISSING_WILL_NOT_FOUND_RESPONSE,
            EmailResponseTemplateCode::FIRST_SECOND_SEARCH_PINCODE_RESPONSE,
            EmailResponseTemplateCode::THIRD_SEARCH_PINCODE_RESPONSE,
        ]);

        if (isset($missingWillSearch)) {
            // Send decease mail here
            $deceasedUser = UserProfile::with(['user' => function ($query) {
                $query->with('livingWill')->has('linkedWillRegistrant');
            }, 'willRegistrant' => function ($query) {
                $query->with('livingWill')->has('linkedWillRegistrant');
            }])->whereRaw("LOWER(first_name) = '" . addslashes(strtolower(trim($deceasedInfo['d_first_name']))) . "'")
                ->whereRaw("LOWER(last_name) = '" . addslashes(strtolower(trim($deceasedInfo['d_last_name']))) . "'")
                ->where('birthdate', $deceasedInfo['d_birth_date'])
                ->first();

            // Check if any records were found
            if ((isset($deceasedUser) && isset($deceasedUser->user) && $deceasedUser->user->livingWill()) ||
                (isset($deceasedUser) && isset($deceasedUser->willRegistrant) && $deceasedUser->willRegistrant->livingWill())
            ) {
                $missingWillSearch->update([
                    'will_found' => true
                ]);
                $templateCode = EmailResponseTemplateCode::FIND_A_MISSING_WILL_FOUND_EMAIL;
                $message = 'Deceased information for ' . ucwords($deceasedInfo['d_first_name'] . ' ' . $deceasedInfo['d_last_name']) . ' was found.';
                $deceaseMessage = $responseTemplates[EmailResponseTemplateCode::FIND_A_MISSING_WILL_FOUND_RESPONSE];
                $deceasedFound = true;
            } else {
                $missingWillSearch->update([
                    'will_found' => false
                ]);
                $templateCode = EmailResponseTemplateCode::FIND_A_MISSING_WILL_NOT_FOUND_EMAIL;
                $message = 'Deceased information for ' . ucwords($deceasedInfo['d_first_name'] . ' ' . $deceasedInfo['d_last_name']) . ' was not found.';
                $deceaseMessage = $responseTemplates[EmailResponseTemplateCode::FIND_A_MISSING_WILL_NOT_FOUND_RESPONSE];
                $deceasedFound = false;
            }

            $maxTransactionCount = Config::get('constants.MAX_TRANSACTION_COUNT');
            if ($mwsInvoice->transaction_count >= 0 && $mwsInvoice->transaction_count < ($maxTransactionCount - 1)) {
                $pinCodeText = $responseTemplates[EmailResponseTemplateCode::FIRST_SECOND_SEARCH_PINCODE_RESPONSE];
                $deceaseMessage = CommonService::replaceTemplateKeywords('#PIN_CODE_TEXT#', '<br /><span class="color-orange">To make use of your remaining search opportunities (3 in total), we have furnished you with a PIN code.</p><div class="my-3 text-center h4">PIN CODE: <strong>#PIN_CODE#</strong></div>', $deceaseMessage);
            } else if ($mwsInvoice->transaction_count === $maxTransactionCount - 1) {
                $pinCodeText = $responseTemplates[EmailResponseTemplateCode::THIRD_SEARCH_PINCODE_RESPONSE];
                $deceaseMessage = CommonService::replaceTemplateKeywords('#PIN_CODE_TEXT#', 'All searches have been completed.</p>', $deceaseMessage);
            } else {
                $pinCodeText = '';
            }
            $keywords = ['#DECEASED_FULL_NAME#', '#PIN_CODE#'];
            $values = [ucwords($deceasedInfo['d_first_name'] . " " . $deceasedInfo['d_last_name']), $mwsInvoice->search_pin ?? ''];
            $deceaseMessage = CommonService::replaceTemplateKeywords($keywords, $values, $deceaseMessage);

            $mwsInvoice->update([
                'transaction_count' => $mwsInvoice->transaction_count + 1
            ]);
            $emailContent = [
                'firstName' => ucfirst($deceasedInfo['d_first_name']),
                'lastName' => ucfirst($deceasedInfo['d_last_name']),
                'userEmail' => $searcherInfo['s_email'],
                'pinCodeText' => $pinCodeText
            ];
            $this->commonService->generateEmailQueue($searcherInfo['s_email'], $templateCode, $emailContent);

            return response()->json([
                'success' => true,
                'message' => $message,
                'deceased_found' => $deceasedFound,
                'deceased_message' => $deceaseMessage,
            ]);
        }
        throw new InvalidDataException('Unable to generate missing will search record.');
    }

    public function verifySearchPin($searchingPin)
    {
        $searchInvoice = MissingWillSearchInvoice::where('search_pin', $searchingPin)->first();
        if (!$searchInvoice) {
            throw new InvalidDataException('Oops!! Searching Pin is invalid.');
        } else if ($searchInvoice->transaction_count >= 3) {
            throw new InvalidDataException('Oops!! No more searches available. You already utilized all 3 searches for this Search PIN Code.');
        }
        return response()->json([
            'success' => true,
            'message' => 'Your search Pin is verified successfully.'
        ], 200);
    }

    public function sendMissingWillSearchInvoiceEmail($mwsInvoice, $searcherInfo, $deceasedInfo, $billingDetails)
    {
        // Send the email
        try {
            Mail::to($searcherInfo['s_email'])
                ->bcc('info@theuswillregistry.org')
                ->send(new WillSearchEmail([
                    'billing_details' => $billingDetails,
                    'state_name' => is_numeric($billingDetails['state'])
                        ? State::whereId($billingDetails['state'])->first()->state_name
                        : $billingDetails['state'],
                    'full_name' => ucwords($searcherInfo['s_first_name'] . ' ' . $searcherInfo['s_last_name']),
                    'deceased_full_name' => ucwords($deceasedInfo['d_first_name'] . ' ' . $deceasedInfo['d_last_name']),
                    'email' => $searcherInfo['s_email'],
                    'search_pin' => $mwsInvoice->search_pin,
                    'price' => $mwsInvoice->invoice->price,
                    'invoice_number' => $mwsInvoice->invoice->invoice_number,
                ]));
            if (Mail::failures()) {
                throw new InvalidDataException('Failed to send an email');
            }
            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully'
            ], 200);
        } catch (Exception $e) {
            throw new InvalidDataException('Failed to send an email: ' . $e->getMessage());
        }
    }

    public function getAllMissingWilSearches()
    {
        $page = (isset($request['page'])) ? $request['page'] : Config::get('constants.PAGE_NO');
        $pageSize = (isset($request['per_page'])) ? $request['per_page'] : Config::get('constants.PAGE_SIZE');

        $missingWillSearch = MissingWillSearch::orderBy('id', 'desc');

        if ($missingWillSearch->get()->isEmpty()) {
            return collect();
        }

        $missingWillSearchResults = $missingWillSearch->paginate($pageSize, ['*'], 'page', $page);

        $missingWillSearchResults->getCollection()->transform(function ($item) {
            return $item->format();
        });

        $missingWillSearchResults->setCollection($missingWillSearchResults->getCollection());

        return $missingWillSearchResults;
    }

    public function getAdvanceMissingWillSearches($request)
    {
        $page = (isset($request['page'])) ? $request['page'] : Config::get('constants.PAGE_NO');
        $pageSize = (isset($request['per_page'])) ? $request['per_page'] : Config::get('constants.PAGE_SIZE');

        $requestFields = [
            's_first_name', 's_last_name', 's_email', 'd_first_name', 'd_last_name',
            'city', 'state', 'county', 'zipcode', 'd_birth_date', 'city'
        ];

        $missingWillSearchQuery = MissingWillSearch::orderBy('id', 'desc');

        foreach ($requestFields as $field) {
            if (isset($request[$field]) && $request[$field] !== '') {
                $missingWillSearchQuery->where($field, $request[$field]);
            }
        }

        if (isset($request['created_from']) && $request['created_from'] !== '') {
            $missingWillSearchQuery->whereDate('created_at', '>=', $request['created_from']);
        }

        if (isset($request['created_to']) && $request['created_to'] !== '') {
            $missingWillSearchQuery->whereDate('created_at', '<=', $request['created_to']);
        }

        $missingWillSearchResults = $missingWillSearchQuery->paginate($pageSize, ['*'], 'page', $page);

        $missingWillSearchResults->getCollection()->transform(function ($item) {
            return $item->format();
        });

        return $missingWillSearchResults;
    }

    public function getMissingWillSearchById($searchId)
    {
        $missingWillSearchById = MissingWillSearch::where('id', $searchId)->orderBy('id', 'desc')
            ->get()
            ->map->getByIdFormat();
        return $missingWillSearchById;
    }

    public function deleteMissingWillById($deleteIdsArray)
    {
        $deletedIds = [];

        foreach ($deleteIdsArray as $deleteId) {
            try {
                $missingWill = MissingWillSearch::withTrashed()->findOrFail($deleteId);
                $missingWill->delete();
            } catch (ModelNotFoundException $exception) {
                $deletedIds[] = $deleteId;
            }
        }
        return $deletedIds;
    }

    public function getLastSevenDaysMissingWillSearches($fromDate)
    {
        $missingWillSearches = MissingWillSearch::where('created_at', '>=', $fromDate)
            ->orderBy('id', 'desc')
            ->get()
            ->map->format();
        return $missingWillSearches;
    }

    // fetched missing will search by start date and end date
    public function processWillSearchReportEmail($request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Fetch start and end date
        $dateRange = $this->commonService->generateDateRange($startDate, $endDate);

        // Dispatch the job to process the missing will search report and send email
        GenerateMissingWillSearchesReport::dispatch($dateRange);

        // // Instantiate the job class
        // $job = new GenerateMissingWillSearchesReport($dateRange);

        // // Call the handle method directly
        // $job->handle();

        return response()->json(['message' => 'Missing will search report processing has been started.']);
    }
}
