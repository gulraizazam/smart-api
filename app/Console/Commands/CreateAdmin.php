<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Database\Seeders\ResourceTypes;
use Database\Seeders\UserTypes;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * User model.
     *
     * @var object
     */
    private $user;

    private $user_type;

    private $resource_type;

    private $account;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        parent::__construct();

        $this->account = new AccountsSeeder();
        $this->user_type = new UserTypes();
        $this->resource_type = new ResourceTypes();

        $this->user = $user;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        /*run required seeders before create admin*/
        /* $this->account->run();
         $this->user_type->run();
         $this->resource_type->run();*/
        /*seeders*/

        $details = $this->getDetails();

        if ($details) {
            $admin = $this->user->create($details);
            $this->display($admin);
        }
    }

    /**
     * Ask for admin details.
     *
     * @return mixed array | boolean
     */
    private function getDetails()
    {
        $details['name'] = $this->ask('Name', 'Super Admin');
        $details['email'] = $this->ask('Email', 'admin@admin.com');
        if ($this->isExist($details['email'])) {
            $this->error('This email already in use, can you enter different one.');

            return false;
        }

        $password = $this->ask('Password', '12345678');
        $confirm_password = $this->ask('Confirm password', '12345678');
        $details['password'] = bcrypt($password);
        $details['confirm_password'] = bcrypt($confirm_password);
        $details['phone'] = '12345678901';
        $details['main_account'] = 1;
        $details['user_type_id'] = 1;
        $details['account_id'] = 1;
        $details['active'] = 1;
        $details['gender'] = 1;

        while (! $this->isValidPassword($password, $confirm_password)) {
            if (! $this->isRequiredLength($password)) {
                $this->error('Password must be more that six characters');

                return false;
            }

            if (! $this->isMatch($password, $confirm_password)) {
                $this->error('Password and Confirm password do not match');

                return false;
            }
        }

        return $details;
    }

    private function isExist($email): bool
    {
        return User::where('email', $email)->exists();
    }

    /**
     * Display created admin.
     *
     * @param  array  $admin
     */
    private function display(User $admin): void
    {
        $headers = ['Name', 'Email', 'Super admin'];

        $fields = [
            'Name' => $admin->name,
            'email' => $admin->email,
            'admin' => 'Admin',
        ];

        $this->info('Super admin created.');
        $this->table($headers, [$fields]);
    }

    /**
     * Check if password is valid
     */
    private function isValidPassword(string $password, string $confirmPassword): bool
    {
        return $this->isRequiredLength($password) &&
            $this->isMatch($password, $confirmPassword);
    }

    /**
     * Check if password and confirm password matches.
     */
    private function isMatch(string $password, string $confirmPassword): bool
    {
        return $password === $confirmPassword;
    }

    /**
     * Checks if password is longer than six characters.
     */
    private function isRequiredLength(string $password): bool
    {
        return strlen($password) > 6;
    }
}
