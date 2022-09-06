<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\User;
use App\Models\ResourceTypes;
use App\Models\DoctorHasLocations;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\Resources;

class DoctorsSeed extends Seeder
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
            'title' => 'Doctors',
            'name' => 'doctors_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);
        Permission::insert([
            [
                'title' => 'Create',
                'name' => 'doctors_create',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'doctors_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'doctors_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'doctors_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Change Password',
                'name' => 'doctors_change_password',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'doctors_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Allocate',
                'name' => 'doctors_allocate',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ]
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('doctors_manage');
        $role->givePermissionTo('doctors_create');
        $role->givePermissionTo('doctors_edit');
        $role->givePermissionTo('doctors_change_password');
        $role->givePermissionTo('doctors_destroy');
        $role->givePermissionTo('doctors_active');
        $role->givePermissionTo('doctors_inactive');
        $role->givePermissionTo('doctors_allocate');

        $resourcetype = ResourceTypes::where('name', '=', 'doctor')->first();
        $roleid = DB::table('roles')->select('id')->where('name', '=', 'administrator')->first();
        $services = Services::where('parent_id','!=','0')->first();


        $doctors = [
            // Lahore Doctors
            [
                'name' => 'Dr Kokab Shahab',
                'phone' => '3214466755',
                'email' => 'Kokab@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 4,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Ayesha Nasir',
                'phone' => '3214466754',
                'email' => 'Ayesha@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 4,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr. Farrukh Aslam Khalid',
                'phone' => '3214466753',
                'email' => 'Farrukh@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 2,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Prof. Dr Ahsan Riaz',
                'phone' => '3214466752',
                'email' => 'Ahsan@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 3,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr.Amin Yousaf',
                'phone' => '3214466751',
                'email' => 'Amin@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 5,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr. Ata-Ul-Haq',
                'phone' => '3214466756',
                'email' => 'Ataulhaq@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 5,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr. Sehrish Riaz',
                'phone' => '3214466757',
                'email' => 'Sehrish@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 7,
                'city_id' => 1,
                'region_id'=> 5,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],

            // Karachi Doctors
            [
                'name' => 'Dr Badie Idrees',
                'phone' => '3214466758',
                'email' => 'Badie@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 7,
                'city_id' => 2,
                'region_id'=> 4,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Uzma Butt',
                'phone' => '3214466759',
                'email' => 'Uzma@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 7,
                'city_id' => 2,
                'region_id'=> 4,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Nazia Siddiqui',
                'phone' => '3214466760',
                'email' => 'Nazia@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 12,
                'city_id' => 2,
                'region_id'=> 4,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Tahira Mughal',
                'phone' => '3214466761',
                'email' => 'Tahiramughal@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 12,
                'city_id' => 2,
                'region_id'=> 4,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            // Islamabad/Rawalpindi Doctors
            [
                'name' => 'Dr. Humayon Mohmand',
                'phone' => '3214466762',
                'email' => 'Humayon@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 8,
                'city_id' => 3,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Tahira',
                'phone' => '3214466763',
                'email' => 'Tahira@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 9,
                'city_id' => 3,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Bilal',
                'phone' => '3214466764',
                'email' => 'Bilal@gmail.com',
                'gender' => '1',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 9,
                'city_id' => 3,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Samia Tariq',
                'phone' => '3214466765',
                'email' => 'Samia@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 11,
                'city_id' => 3,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Khadija Farhan',
                'phone' => '3214466766',
                'email' => 'Khadija@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 11,
                'city_id' => 3,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            // Peshawar Doctors
            [
                'name' => 'Dr Kainat Bibi',
                'phone' => '3214466767',
                'email' => 'Kainat@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 13,
                'city_id' => 4,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            [
                'name' => 'Dr Amina Ali',
                'phone' => '3214466768',
                'email' => 'Amina@gmail.com',
                'gender' => '2',
                'user_type_id' => config('constants.practitioner_id'),
                'resource_type_id' => $resourcetype->id,
                'account_id' => 1,
                'location_id' => 13,
                'city_id' => 4,
                'region_id'=> 3,
                'password' => bcrypt('password'),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ];

        foreach ($doctors as $doctor) {
            $userObj = $doctor;
            unset($userObj['location_id']);
            unset($userObj['city_id']);
            unset($userObj['region_id']);

            if ($user = User::create($userObj)) {
                $user->assignRole('administrator');

                $resource = new Resources();
                $resource->name = $doctor['name'];
                $resource->account_id = 1;
                $resource->resource_type_id = $resourcetype->id;
                $resource->external_id = $user->id;
                $resource->save();


                RoleHasUsers::create(array(
                    'role_id' => $roleid->id,
                    'user_id' => $user->id,
                ));


                DoctorHasLocations::create(array(
                    'location_id' => $doctor['location_id'],
                    'service_id' => $services->id,
                    'user_id' => $user->id,
                ));
            }
        }
    }
}
