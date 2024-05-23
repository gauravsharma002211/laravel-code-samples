<?php

namespace App\Services;

use App\Enums\EmailResponseTemplateCode;
use App\Models\EmailQueue;
use App\Models\EmailResponseTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CommonService
{

    public function addContactInquiry($contactData)
    {
        // pass email payload to 
        $templateCode = EmailResponseTemplateCode::CONTACT_US;
        $this->generateEmailQueue($contactData['email'], $templateCode);
        $adminUsers = User::whereHas("roles", function ($q) {
            $q->where("name", "admin");
        })->get();
        $emailContent = [
            'userEmail' => $contactData['email'],
            'fullName' => $contactData['name'],
            'subject' => $contactData['subject'],
            'message' => $contactData['message'],
        ];
        foreach ($adminUsers as $admin) {
            $this->generateEmailQueue($admin->email, EmailResponseTemplateCode::ADMIN_CONTACT_US, $emailContent);
        }
        return response()->json([
            'success' => true,
            'message' => "We have received your contact inquiry. We will contact you soon."
        ]);
    }

    public function addFAQInquiry($faqData)
    {
        return response()->json([
            'success' => true,
            'message' => "We have received your inquiry. We will answer for your question soon."
        ]);
    }

    public function addNonProfitInquiry($nonProfitData)
    {
        return response()->json([
            'success' => true,
            'message' => "We have received your inquiry. We will contact you soon"
        ]);
    }

    public function generateWillDocumentName($user)
    {
        return trim($user->profile->first_name . ' ' . $user->profile->last_name . ' Will Document');
    }

    public function generateWillDocumentFileName($user)
    {
        return str_replace(" ", "-", $this->generateWillDocumentName($user) . time() . '.pdf');
    }

    public function getWillDocumentDirectory($userId)
    {
        $path = Config::get('constants.WILL_DOCUMENT_PATH') . '/' . $userId;

        if (!File::isDirectory(public_path($path))) {
            File::makeDirectory(public_path($path), 0777, true, true);
        }
        return $path;
    }

    public function removeFileFromDirectory($fileName, $directoryPath)
    {
        if (File::isFile($directoryPath . '/' . $fileName)) {
            File::delete($directoryPath . '/' . $fileName);
        }
    }

    public function formatErrorMessages($errors)
    {
        $messages = [];
        foreach ($errors as $key => $message) {
            $messages[$key] = array_shift($message);
        }
        return $messages;
    }

    public function generateUniqueCode($prefix)
    {
        $date = now()->format('ymd');
        $randomNumber = random_int(100000, 999999);
        $invoiceNumber = $prefix . $date . $randomNumber;

        return $invoiceNumber;
    }

    public function randomPin($length = 6, $number = true)
    {
        if ($number) {
            return random_int(intval(str_pad('1', $length, '0')), intval(str_pad('9', $length, '9')));
        }
        return Str::random($length);
    }

    public static function formatPhoneNumber($phoneNumber)
    {
        if (strlen($phoneNumber) === 10) {
            $areaCode = substr($phoneNumber, 0, 3);
            $part1 = substr($phoneNumber, 3, 3);
            $part2 = substr($phoneNumber, 6, 4);

            return '(' . $areaCode . ') ' . $part1 . '-' . $part2;
        }

        return $phoneNumber;
    }

    public function getReplacedTemplate($email, $templateCode, $emailContent = null)
    {
        $emailResponseTemplate = EmailResponseTemplate::where('code', $templateCode)->active()->first();

        if (!$emailResponseTemplate) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        if ($emailContent) {
            $emailResponseTemplate->content = str_replace("#FULL_NAME#", $emailContent['fullName'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#FIRST_NAME#", $emailContent['firstName'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#LAST_NAME#", $emailContent['lastName'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#EMAIL#", $emailContent['userEmail'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#SUBJECT#", $emailContent['subject'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#MESSAGE#", $emailContent['message'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#REPLACE_PINCODE_TEXT#", $emailContent['pinCodeText'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#ORGANIZATION_NAME#", $emailContent['organization_name'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#ORGANIZATION_WEBSITE#", $emailContent['organization_website'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#PHONE_NUMBER#", $emailContent['phone_number'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#LOGIN_LINK#", isset($emailContent['login_link']) ? '<a href="' . $emailContent['login_link'] . '" target="_blank">Click Here</a>' : null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#BIRTH_DATE#", $emailContent['birthDate'] ?? null, $emailResponseTemplate->content);
            $emailResponseTemplate->content = str_replace("#DEATH_DATE#", $emailContent['deathDate'] ?? null, $emailResponseTemplate->content);
        }

        $emailTemplateSubject = $emailResponseTemplate->subject !== ''
            ? $emailResponseTemplate->subject
            : $emailResponseTemplate->title;

        $html = view('emails.templateContent', ['content' => $emailResponseTemplate->content])->render();

        $requestData = [
            'email'    => $email,
            'type'     => $templateCode,
            'content'  => $html,
            'subject'  => $emailTemplateSubject,
            'attachments' => isset($emailContent['attachments']) && $emailContent['attachments'] != ''
                ? $emailContent['attachments'] : null,
        ];
        return $requestData;
    }
    public function generateEmailQueue($email, $templateCode, $emailContent = null)
    {
        $requestData = $this->getReplacedTemplate($email, $templateCode, $emailContent);

        // Check if the response is a JSON response indicating an error
        if (is_a($requestData, 'Illuminate\Http\JsonResponse')) {
            return null;
        }
        return EmailQueue::create($requestData);
    }

    public static function getFormattedRelationship($relationship)
    {
        switch ($relationship) {
            case "partner":
                return 'Partner';
            case "spouse":
                return "Spouse";
            case "child":
                return "Child";
            case "grandchild":
                return "Grand Child";
            case "stepchild":
                return "Step Child";
            case "parent":
                return "Parent";
            case "grandparent":
                return "Grand Parent";
            case "stepparent":
                return "Step Parent";
            case "sibling":
                return "Sibling";
            case "cousin":
                return "Cousin";
            case "relative":
                return "Relative";
            case "friend":
                return "Friend";
            case "other":
                return "Other";
            default:
                return ucwords($relationship);
        }
    }

    public static function getFormattedNonProbateAssetType($type)
    {

        switch ($type) {
            case 'jointly-owned-property':
                return 'Jointly Owned Property';
            case 'joint-bank-account':
                return 'Joint Bank Account';
            case 'retirement-account':
                return 'Retirement Account';
            case 'life-insurance-policy':
                return 'Life Insurance Policy';
            case 'tod-account':
                return 'Transfer-on-Death (TOD) Account';
            case 'pod-account':
                return 'Payable-on-Death (POD) Account';
            case 'revocable-living-trusts':
                return 'Revocable Living Trust';
            case 'annuity':
                return 'Annuity';
            case 'brokerage-account':
                return 'Brokerage Accounts with Transfer-on-Death (TOD) Designation';
            case 'hsa-account':
                return 'Health Savings Accounts (HSAs) with Beneficiary Designation';
            case '529-college-saving-plan':
                return '529 College Savings Plans with Beneficiary Designation';
            default:
                return ucwords($type);
        }
    }

    public function formatTemplateType($type)
    {
        if (ctype_upper($type[0])) {
            $templateType = Config::get('constants.TEMPLATE_TYPE')[strtoupper($type)] ?? $type;
        } else {
            $templateType = ucfirst(strtolower($type));
        }
        return $templateType;
    }

    public function insertEmailQueue($requestData)
    {
        return EmailQueue::create($requestData);
    }

    public function generateNewDirectory($path)
    {
        $directoryPath = $path . '/';

        if (!File::isDirectory(public_path($directoryPath))) {
            File::makeDirectory(public_path($directoryPath), 0777, true, true);
        }
        return $directoryPath;
    }

    public function generateFileName($baseName, $id, $fileExtension)
    {
        $currentDateTime = str_replace([' ', ':'], ['_', '-'], Carbon::now());
        $generatedFileName = "{$baseName}_{$id}_{$currentDateTime}.{$fileExtension}";
        return $generatedFileName;
    }

    public function generateNewFileName($filename, $fileExtension = '.pdf')
    {
        $currentDateTime = now()->format('Y-m-d_H-i-s');
        $generatedFileName = $filename . '_' . $currentDateTime . $fileExtension;
        return str_replace(' ', '-', $generatedFileName);
    }

    public static function replaceTemplateKeywords($keywords, $values, $content)
    {
        if ($content) {
            if (is_array($keywords) && is_array($values) && sizeof($keywords) == sizeof($values)) {
                foreach ($keywords as $i => $keyword) {
                    $content = str_replace($keyword, $values[$i], $content);
                }
            } else if ($keywords != '' && $values != '') {
                $content = str_replace($keywords, $values, $content);
            } else {
            }
        }
        return $content;
    }

    public static function  formatDate($date, $format = 'M d, Y')
    {
        return $date ? date($format, strtotime($date)) : null;
    }

    public static function  generatePdfName($page_title)
    {
        return Str::slug($page_title, '-');
    }

    public static function getResponseTemplates($templateCodes)
    {
        $responses = EmailResponseTemplate::where('type', 'response')
            ->whereIn('code', $templateCodes)
            ->select('code', 'content')
            ->get()->toArray();
        return collect($responses)->mapWithKeys(function ($item) {
            return [$item['code'] => $item['content']];
        })->toArray();
    }

    public static function generateDateRange($startDate = null, $endDate = null)
    {
        // If $startDate is provided and it's in "mm-dd-yyyy" format, parse it directly.
        if ($startDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $startDate)) {
            $startDate = Carbon::createFromFormat('m-d-Y', $startDate)->startOfDay();
        } elseif ($startDate && preg_match('/^\d{2}-\d{4}$/', $startDate)) {
            // If $startDate is provided and it's in "mm-yyyy" format, parse it as the start of the month.
            $startDate = Carbon::createFromFormat('m-Y', $startDate)->startOfMonth();
        } else {
            // If $startDate is not provided, default to 30 days ago from the current date.
            $startDate = Carbon::now()->subDays(30)->startOfDay();
        }

        // If $endDate is provided and it's in "mm-dd-yyyy" format, parse it directly.
        if ($endDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $endDate)) {
            $endDate = Carbon::createFromFormat('m-d-Y', $endDate)->endOfDay();
        }
        // If $endDate is provided and it's in "mm-yyyy" format, parse it as the end of the month.
        elseif ($endDate && preg_match('/^\d{2}-\d{4}$/', $endDate)) {
            $endDate = Carbon::createFromFormat('m-Y', $endDate)->endOfMonth();
        }
        // If $endDate is not provided, default to the current date.
        else {
            $endDate = Carbon::now()->endOfDay();
        }

        // Return an array containing the formatted $startDate and $endDate.
        return [$startDate->toDateTimeString(), $endDate->toDateTimeString()];
    }
}
