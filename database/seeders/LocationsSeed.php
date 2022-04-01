<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Locations;
use App\Models\ServiceHasLocations;
use App\Models\Services;

class LocationsSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Centres',
            'name' => 'locations_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'locations_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'locations_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'locations_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'locations_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'locations_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Sort',
                'name' => 'locations_sort',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('locations_manage');
        $role->givePermissionTo('locations_create');
        $role->givePermissionTo('locations_edit');
        $role->givePermissionTo('locations_active');
        $role->givePermissionTo('locations_inactive');
        $role->givePermissionTo('locations_destroy');
        $role->givePermissionTo('locations_sort');

        $services = Services::where('parent_id','!=','0')->first();

        $locations = [
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Shumaila Ashraf',
                'fdo_phone' => '3444458793',
                'address' => '49, E Block, Maulana Shaukat Ali Road Johar Town,Lahore',
                'google_map' => 'https://goo.gl/maps/UNQKTGDzdNo',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>1,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'LaForma Clinic',
                'fdo_name' => 'Misbah Khan',
                'fdo_phone' => '3444455613',
                'address' => '41-B, Khayabane Firdousi, Johar Town, Lahore',
                'google_map' => 'https://goo.gl/maps/ScJakDrjKbQ2',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>2,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'PLASTHETICS CLINIC',
                'fdo_name' => '',
                'fdo_phone' => '',
                'address' => 'Bungalow # 22 G Block, Johar Town, Lahore',
                'google_map' => 'https://goo.gl/maps/vdnYemSGcDH2',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>3,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Ayesha Bashir',
                'fdo_phone' => '3444458794',
                'address' => '200, Y Block, Commercial Area, Phase-3 DHA, Lahore',
                'google_map' => 'https://goo.gl/maps/afxxfAn7i2v',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>4,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'Cosmoplast Lahore',
                'fdo_name' => 'Saba Sabahat',
                'fdo_phone' => '3444458795',
                'address' => '14-DD, Commercial Area Phase 4, DHA Lahore',
                'google_map' => 'https://goo.gl/maps/vobrAbSUDhE2',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>5,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'All Perfect Beauty',
                'fdo_name' => 'Muqaddas Hussain',
                'fdo_phone' => '3444455614',
                'address' => '78 CCA, Phase 5, Second cup building, 3rd floor, Commercial zone, DHA, Lahore',
                'google_map' => 'https://goo.gl/maps/3GtLd3LKdHM2',
                'city_id' => 1,
                'region_id'=> 5,
                'sort_no'=>6,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Hina Khero/Mahnoor Khan',
                'fdo_phone' => '3444458798',
                'address' => 'Bungalow No. D-92/I, Block4 Clifton, Ibn-e-Qasim Road, Karachi',
                'google_map' => 'https://goo.gl/maps/H6SHViLTBjD2',
                'city_id' => 2,
                'region_id'=> 4,
                'sort_no'=>7,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'La Chirurgie',
                'fdo_name' => 'Anam Akhtar',
                'fdo_phone' => '3444458797',
                'address' => 'Unit no. 1-B, Opposite Excel Lab Next to Ali Medical, Main Kohistan Road, F-8/3, Islamabad',
                'google_map' => 'https://goo.gl/maps/B7xLUNcx33p',
                'city_id' => 3,
                'region_id'=> 3,
                'sort_no'=>8,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'LE LOTUS CLINIQUE',
                'fdo_name' => 'Yusra',
                'fdo_phone' => '3355181166',
                'address' => 'Crystal Heights, Plaza # 14, Main GT Road, DHA Gate # 1 Commercial Area Phase 2 DHA, Islamabad',
                'google_map' => 'https://goo.gl/maps/UeuMs2AHfLQ2',
                'city_id' => 3,
                'region_id'=> 3,
                'sort_no'=>9,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Sabeela Khan',
                'fdo_phone' => '3444458796',
                'address' => 'Jahangir Multiplex Adj. Quad-e-Azam International Hospital Main Peshawar Road, Rawalpindi',
                'google_map' => 'https://goo.gl/maps/QHnpoWAsEsG2',
                'city_id' => 3,
                'region_id'=> 3,
                'sort_no'=>10,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'Lipo Clinic',
                'fdo_name' => 'Sabeela Khan',
                'fdo_phone' => '3444458796',
                'address' => 'Suite # 1, 2nd Floor, One The Mall, Mall Road, Saddar, Rawalpindi',
                'google_map' => 'https://goo.gl/maps/nE9HgP3GfT92',
                'city_id' => 3,
                'region_id'=> 3,
                'sort_no'=>11,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => 'Caviar',
                'fdo_name' => 'Faiza Khan',
                'fdo_phone' => '3202889242',
                'address' => 'Plot # 23-D, Lane 4, Shahbaz Commmercial, DHA Phase 6, Karachi',
                'google_map' => 'https://goo.gl/maps/tbH2uPeQH632',
                'city_id' => 2,
                'region_id'=> 4,
                'sort_no'=>12,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'custom',
                'name' => '3D Lifestyle Center of Medical Aesthetics',
                'fdo_name' => 'Saba Khan',
                'fdo_phone' => '3444455618',
                'address' => '3rd floor, Bank of Khyber, Adjecent Sultan CNG station, Hayatabad Ring Road, Peshawar',
                'google_map' => 'https://goo.gl/maps/X786jMFA6du',
                'city_id' => 4,
                'region_id'=> 3,
                'sort_no'=>13,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'all',
                'name' => 'All Centres',
                'address' => '',
                'google_map' => '',
                'city_id' => 5,
                'region_id'=> 6,
                'sort_no'=>14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id'=>'1',
            ],
            [
                'slug' => 'region',
                'name' => 'All East Region',
                'address' => '',
                'google_map' => '',
                'city_id' => 7,
                'region_id' => 1,
                'sort_no' => 14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ],
            [
                'slug' => 'region',
                'name' => 'All West Region',
                'address' => '',
                'google_map' => '',
                'city_id' => 7,
                'region_id' => 2,
                'sort_no' => 14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ],
            [
                'slug' => 'region',
                'name' => 'All North Region',
                'address' => '',
                'google_map' => '',
                'city_id' => 8,
                'region_id' => 3,
                'sort_no' => 14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ],
            [
                'slug' => 'region',
                'name' => 'All South Region',
                'address' => '',
                'google_map' => '',
                'city_id' => 9,
                'region_id' => 4,
                'sort_no' => 14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ],
            [
                'slug' => 'region',
                'name' => 'All Central Region',
                'address' => '',
                'google_map' => '',
                'city_id' => 10,
                'region_id' => 5,
                'sort_no' => 14,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'account_id' => '1',
            ]
        ];

        foreach ($locations as $location) {
            $userObj = $location;
            if ($loc = Locations::create($userObj)) {
                ServiceHasLocations::create(array(
                    'location_id' => $loc->id,
                    'service_id' => $services->id,
                    'account_id' => $loc->account_id,
                ));
            }
        }
    }
}
