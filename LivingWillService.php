<?php

namespace App\Services;

use App\Enums\LivingWillType;
use App\Enums\UserType;
use App\Events\UserRegistration;
use App\Events\WillRegistrationUpdate;
use App\Models\LivingWill;
use App\Models\LivingWillLocation;
use App\Models\LivingWillBeneficiary;
use App\Models\WillRegistrant;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use App\Services\CommonService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Config;
use App\Services\PdfGeneratorService;

class LivingWillService
{
    protected $commonService, $pdfGeneratorService;
    private $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->commonService = new CommonService();
        $this->pdfGeneratorService = new PdfGeneratorService();
    }

    public function hasLivingWill(int $userId)
    {
        return LivingWill::with('user')
            ->whereHas('user', function ($query) use ($userId) {
                $query->where('will_creator_id', $userId)
                    ->orWhere('will_registrant_id', $userId);
            })->exists();
    }

    public function createLivingWill($requestData)
    {
        // check if user id exist or not from given request
        $user = $this->getOrCreateUserDetails($requestData['personalInfo']);
        if (isset($user) && !isset($user->livingWill)) {
            $livingWillCode = $this->commonService->generateUniqueCode('WREG');
            $user->livingWill()->create([
                'will_code' => $livingWillCode,
                'user_id' => $user->linkedWillRegistrant->id
            ]);

            $user = $user->load('livingWill');
            $livingWillTypes = [
                LivingWillType::Original => $requestData['originalWillLocation'],
                LivingWillType::Trust => $requestData['trustLocation']
            ];

            if (!empty($requestData['duplicateWillLocation']) && isset($requestData['duplicateWillLocation'])) {
                $livingWillTypes[LivingWillType::Duplicate] = $requestData['duplicateWillLocation'];
            }

            $ownerName = "{$user->profile['first_name']} {$user->profile['middle_name']} {$user->profile['last_name']}";
            $this->updateOrCreateLocations($user->livingWill->id, $livingWillTypes);

            $this->updateOrCreateBeneficiaries($user->livingWill->id, $requestData['livingWillBeneficiaries']);

            $this->generateCeritificate($ownerName);
            event(new UserRegistration($user, 'will_registration'));

            return true;
        }
        return false;
    }

    public function getLivingWill($user)
    {
        $personalInfo = $user->profile->only([
            'first_name', 'last_name', 'birthdate', 'phone', 'gender',
            'address_1', 'address_2', 'city', 'zipcode'
        ]);

        if ($user->profile->state_id) {
            $personalInfo['state'] = $user->state->id;
            $personalInfo['state_abbr'] = $user->state->state_abbr;
        }
        if ($user->profile->county_id) {
            $personalInfo['county'] = $user->profile->county_id;
            $personalInfo['county_name'] = $user->profile->county->county_name;
        }
        if ($user->livingWill) {
            return $this->mapWillLocations($user->livingWill, $personalInfo);
        }
    }

    public function updateLivingWillById($requestData)
    {
        $user = $this->getOrCreateUserDetails($requestData['personalInfo']);

        if (isset($user) && isset($user->livingWill)) {

            $livingWillTypes = [
                LivingWillType::Original => $requestData['originalWillLocation'],
                LivingWillType::Trust => $requestData['trustLocation']
            ];
            if (!empty($requestData['duplicateWillLocation']) && isset($requestData['duplicateWillLocation'])) {
                $livingWillTypes[LivingWillType::Duplicate] = $requestData['duplicateWillLocation'];
            }

            $this->updateOrCreateLocations($user->livingWill->id, $livingWillTypes);

            $this->updateOrCreateBeneficiaries($user->livingWill->id, $requestData['livingWillBeneficiaries']);

            event(new WillRegistrationUpdate($user));
            return true;
        }
        return false;
    }

    private function updateOrCreateLocations($willId, $locationDataArray)
    {
        foreach ($locationDataArray as $type => $locationData) {
            // Handle optional fields for Trust type
            $locationData = $locationDataArray[$type] ?? null;

            if ($type === LivingWillType::Trust && !empty($locationData['is_trust_stored_with_will'])) {
                $locationData['will_location'] = $locationData['will_location'] ?? null;
                $optionalFields = ['address1', 'address2', 'city', 'zipcode', 'state_id', 'county_id'];

                foreach ($optionalFields as $field) {
                    $locationData[$field] = $locationData[$field] ?? null;
                }
            }

            // Update or create LivingWillLocation
            $location = LivingWillLocation::updateOrCreate(
                ['will_id' => $willId, 'will_type' => $type],
                $locationData
            );

            // Update additional fields
            $location->fill($locationData);
            $location->save();
        }

        $existingLocations = LivingWillLocation::where('will_id', $willId)->get();
        foreach ($existingLocations as $existingLocation) {
            $type = $existingLocation->will_type;

            if (!isset($locationDataArray[$type])) {
                $existingLocation->delete();
            }
        }
    }

    private function updateOrCreateBeneficiaries($willId, $beneficiaryDataArray)
    {
        // Get all existing beneficiaries for the given will_id
        $existingBeneficiaries = LivingWillBeneficiary::where('will_id', $willId)->get();

        // Create an array of existing beneficiary IDs for easy comparison
        $existingBeneficiaryIds = $existingBeneficiaries->pluck('id')->toArray();

        foreach ($beneficiaryDataArray as $data) {
            if (isset($data['id'])) {
                // Update existing record
                LivingWillBeneficiary::where('id', $data['id'])
                    ->where('will_id', $willId)
                    ->update([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'relationship' => $data['relationship'],
                    ]);

                // Remove the ID from the array of existing IDs
                $key = array_search($data['id'], $existingBeneficiaryIds);
                if ($key !== false) {
                    unset($existingBeneficiaryIds[$key]);
                }
            } else {
                // Create new record
                LivingWillBeneficiary::create([
                    'will_id' => $willId,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'relationship' => $data['relationship'],
                ]);
            }
        }

        // Delete remaining records that were not present in the provided array
        LivingWillBeneficiary::whereIn('id', $existingBeneficiaryIds)->delete();
    }

    private function mapWillLocations($locations, $personalInfo)
    {
        $data = [
            'personalInfo' => $personalInfo,
            'originalWillLocation' => [],
            'duplicateWillLocation' => [],
            'trustLocation' => [],
            'livingWillBeneficiaries' => [],
            'living_wills' => $locations->only(['will_code', 'user_id'])
        ];

        foreach ($locations->willLocations as $location) {
            $locationData = [
                'id' => $location->id,
                'will_type' => $location->will_type,
                'will_location' => $location->will_location,
                'address1' => $location->address1,
                'address2' => $location->address2,
                'city' => $location->city,
                'zipcode' => $location->zipcode,
                'state' => ($location->state) ? $location->state->id : null,
                'state_abbr' => ($location->state) ? $location->state->state_abbr : null,
                'county' => ($location->county) ? $location->county->id : null,
                'county_name' => ($location->county) ? $location->county->county_name : null,
                'is_trust_stored_with_will' => $location->is_trust_stored_with_will,
                'trust_stored_with' => $location->trust_stored_with,

                'home_location' => $location->home_location,
                'home_email' => strtolower($location->home_email),
                'home_phone' => $location->home_phone,

                'attorney_phone' => $location->attorney_phone,
                'attorney_email' => strtolower($location->attorney_email),
                'attorney_first_name' => $location->attorney_first_name,
                'attorney_last_name' => $location->attorney_last_name,
                'attorney_firm_name' => $location->attorney_firm_name,

                'business_phone' => $location->business_phone,
                'business_email' => strtolower($location->business_email),
                'business_name' => $location->business_name,
                'business_contact_name' => $location->business_contact_name,

                'private_phone' => $location->private_phone,
                'private_email' => strtolower($location->private_email),
                'private_contact_first_name' => $location->private_contact_first_name,
                'private_contact_last_name' => $location->private_contact_last_name,
                'private_contact_relationship' => $location->private_contact_relationship,

                'icloud_website' => $location->icloud_website,
                'icloud_email' => strtolower($location->icloud_email),
            ];

            if ($location->will_type === LivingWillType::Original) {
                $data['originalWillLocation'] = $locationData;
            } elseif ($location->will_type === LivingWillType::Duplicate) {
                $data['duplicateWillLocation'] = $locationData;
            } elseif ($location->will_type === LivingWillType::Trust) {
                $data['trustLocation'] = $locationData;
            }
        }

        foreach ($locations->beneficiaries as $beneficiary) {
            $data['livingWillBeneficiaries'][] = [
                'id' => $beneficiary->id,
                'will_id' => $beneficiary->will_id,
                'first_name' => $beneficiary->first_name,
                'last_name' => $beneficiary->last_name,
                'relationship' => $beneficiary->relationship
            ];
        }

        return $data;
    }

    protected function getOrCreateUserDetails($userRequestData)
    {
        if (isset($userRequestData['will_creator_id'])) {
            $user = $this->userRepository->findById($userRequestData['will_creator_id']);
            if (!isset($user->linkedWillRegistrant)) {
                $user->linkedWillRegistrant()->create([
                    'will_creator_id' => $user->id,
                ]);
            }
            $user = $user->load(['profile', 'livingWill', 'linkedWillRegistrant', 'livingWill.beneficiaries', 'livingWill.willLocations'])->refresh();
            return $user;
        } else if (isset($userRequestData['user_id'])) {
            $user = $this->userRepository->findWillRegistrantById($userRequestData['user_id']);
            if (isset($user)) {
                $user->profile->update($userRequestData);
                return $user;
            }
            return null;
        } else {
            $user = WillRegistrant::firstOrCreate(
                ['email' => strtolower($userRequestData['email'])],
                [
                    'password' => Hash::make($userRequestData['password']),
                ]
            );
            $user->linkedWillRegistrant()->create([
                'will_registrant_id' => $user->id,
            ]);
            $role = Role::findByName(UserType::Owner, 'api');
            $user->assignRole($role);
            $profileFields = [
                'first_name', 'last_name', 'birthdate', 'phone', 'gender',
                'address_1', 'address_2', 'city', 'zipcode', 'state_id', 'country', 'county_id'
            ];
            $profileData = array_intersect_key(
                $userRequestData,
                array_flip($profileFields)
            );
            if (!isset($user->profile)) {
                $user->profile()->create($profileData);
            } else {
                $user->profile->update($profileData);
            }
            $user = $user->load(['profile', 'livingWill', 'linkedWillRegistrant', 'livingWill.beneficiaries', 'livingWill.willLocations'])->refresh();
            return $user;
        }
    }

    /**
     * Retrieve all living will records for admin panel.
     *
     * @return \Illuminate\Support\Collection
     *   A collection of formatted living will records.
     */
    public function getAllLivingWill()
    {
        $request = request();
        $page = (isset($request['page'])) ? $request['page'] : Config::get('constants.PAGE_NO');
        $pageSize = (isset($request['per_page'])) ? $request['per_page'] : Config::get('constants.PAGE_SIZE');

        $livingWillsQuery = LivingWill::latest();

        if ($livingWillsQuery->count() === 0) {
            return collect();
        }

        $livingWillResults = $livingWillsQuery->paginate($pageSize, ['*'], 'page', $page);

        $livingWillResults->getCollection()->transform(function ($item) {
            return $item->format();
        });

        $livingWillResults->setCollection($livingWillResults->getCollection());

        return $livingWillResults;
    }

    /**
     * Retrieve a LivingWill record by its unique identifier.
     *
     * @param  int  $id  The ID of the LivingWill record to retrieve.
     * @return \App\Models\LivingWill|null  The LivingWill model instance or null if not found.
     */
    public function getLivingWillById($id)
    {
        $livingWill = LivingWill::with(['user', 'beneficiaries', 'willLocations'])->find($id);
        if (!$livingWill) {
            return null;
        }
        $user = $livingWill->user->user ?? $livingWill->user->willRegistrant;
        return $this->getLivingWill($user);
    }

    /**
     * Delete a Living Will by its ID.
     *
     * @param int $id The ID of the Living Will to be deleted.
     *
     * @return bool|null True if the Living Will was deleted successfully, false otherwise.
     */
    public function deleteLivingWillById($id)
    {
        $livingWill = LivingWill::find($id);
        return (!$livingWill) ? null : $livingWill->delete();
    }

    public function generateCeritificate($ownerName)
    {
        $this->pdfGeneratorService->generateLivingWillCertificate($ownerName);
    }
}
