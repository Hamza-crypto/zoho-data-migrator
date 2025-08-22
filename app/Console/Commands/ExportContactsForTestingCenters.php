<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportContactsForTestingCenters extends Command
{
    protected $signature = 'export:testing-centers';
    protected $description = 'Export contacts + accounts into testing center CSV format';

    public function handle()
    {
        $filePath = storage_path('app/testing_centers.csv');
        $fp = fopen($filePath, 'w');

        // ✅ Header row
        fputcsv($fp, [
            'Name',
            'Address Line 1',
            'Address Line 2',
            'City',
            'State',
            'Zipcode',
            'Starting Grade',
            'Ending Grade',
            'Allow Pre-Test (Y or N)',
            'Is this a Parish? (Y or N)',
            'Theology (Y or N)',
            'Math (Y or N)',
            'Reading (Y or N)',
            'First Name',
            'Last Name',
            'Sex (M or F)',
            'Email',
            'Is principal?',
            'Zoho Id',
            'Catholic (Y or N)',
        ]);

        $rows = DB::table('contacts')
            ->leftJoin('accounts', 'contacts.account_id', '=', 'accounts.zoho_id')
            ->select(
                'accounts.zoho_id as zoho_id',
                'accounts.name as account_name',
                'accounts.website',
                'accounts.phone',
                'accounts.zip as account_zip',
                'accounts.street as account_street',
                'accounts.city as account_city',
                'accounts.state as account_state',
                'accounts.country as account_country',
                'accounts.type as account_type',
                'accounts.starting_grade as starting_grade',
                'accounts.ending_grade as ending_grade',
                'contacts.first_name',
                'contacts.last_name',
                'contacts.email',
                'contacts.title',
                'contacts.street as contact_street',
                'contacts.city as contact_city',
                'contacts.state as contact_state',
                'contacts.zip as contact_zip',
                'contacts.country as contact_country',
                'contacts.account_name as contact_account_name'
            )
            ->get();

        foreach ($rows as $row) {
            $street  = ($row->contact_street && $row->contact_street !== '0') ? $row->contact_street : $row->account_street;
            $city    = ($row->contact_city && $row->contact_city !== '0') ? $row->contact_city : $row->account_city;
            $state   = ($row->contact_state && $row->contact_state !== '0') ? $row->contact_state : $row->account_state;
            $zip     = ($row->contact_zip && $row->contact_zip !== '0') ? $row->contact_zip : $row->account_zip;
            $country = ($row->contact_country && $row->contact_country !== '0') ? $row->contact_country : $row->account_country;

            $isParish   = ($row->account_type && strtolower($row->account_type) === 'parish') ? 'Y' : 'N';
            $isCatholic = ($row->account_type && strtolower($row->account_type) === 'diocese') ? 'Y' : 'N';
//dd($row);
            // ✅ Build export row
            $exportRow = [
                $row->account_name ?? $row->contact_account_name, // Name
                $street ?? '',
                '', // Address Line 2 (not in schema, left blank)
                $city ?? '',
                $state ?? '',
                $zip ?? '',
                $row->starting_grade,
                $row->ending_grade,
                'N',  // Allow Pre-Test
                $isParish, // Parish? (from account.type)
                'N',  // Theology (static)
                'N',  // Math
                'N',  // Reading
                $row->first_name ?? '',
                $row->last_name ?? '',
                '', // Sex (not in schema)
                $row->email ?? '',
                $row->title && strtolower($row->title) === 'principal' ? 'Y' : 'N',
                $row->zoho_id, // Fake Salesforce Id
                $isCatholic,
            ];

            fputcsv($fp, $exportRow);
        }

        fclose($fp);
        $this->info("✅ Export complete → {$filePath}");
    }
}
