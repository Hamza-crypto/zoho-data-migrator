<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class CategoryMappingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            [
                'old_category_id' => 154000211831,
                'zoho_category_id' => 1120359000002243035,
                'category_name' => 'Administrative Rostering',
            ],
            [
                'old_category_id' => 154000296993,
                'zoho_category_id' => 1120359000002243086,
                'category_name' => 'ARK Glossaries',
            ],
            [
                'old_category_id' => 154000204962,
                'zoho_category_id' => 1120359000002243001,
                'category_name' => 'Default Category',
            ],
            [
                'old_category_id' => 154000204963,
                'zoho_category_id' => 1120359000002243018,
                'category_name' => 'Getting Started',
            ],
            [
                'old_category_id' => 154000211833,
                'zoho_category_id' => 1120359000002243069,
                'category_name' => 'Reporting',
            ],
            [
                'old_category_id' => 154000211832,
                'zoho_category_id' => 1120359000002243052,
                'category_name' => 'Proctoring & Testing',
            ],
            [
                'old_category_id' => 154000200854,
                'zoho_category_id' => 1120359000002508435,
                'category_name' => 'Grade 2',
            ],
            [
                'old_category_id' => 154000200855,
                'zoho_category_id' => 1120359000002508448,
                'category_name' => 'Grade 3',
            ],
            [
                'old_category_id' => 154000200856,
                'zoho_category_id' => 1120359000002508461,
                'category_name' => 'Grade 4',
            ],
            [
                'old_category_id' => 154000200857,
                'zoho_category_id' => 1120359000002623974,
                'category_name' => 'Grade 5',
            ],
            [
                'old_category_id' => 154000200858,
                'zoho_category_id' => 1120359000002508479,
                'category_name' => 'Grade 6',
            ],
            [
                'old_category_id' => 154000200860,
                'zoho_category_id' => 1120359000002623989,
                'category_name' => 'Grade 7',
            ],
            [
                'old_category_id' => 154000200861,
                'zoho_category_id' => 1120359000002624000,
                'category_name' => 'Grade 8',
            ],
            [
                'old_category_id' => 154000200862,
                'zoho_category_id' => 1120359000002670011,
                'category_name' => 'Grade 9',
            ],
            [
                'old_category_id' => 154000200863,
                'zoho_category_id' => 1120359000002670022,
                'category_name' => 'Grade 10',
            ],
            [
                'old_category_id' => 154000200866,
                'zoho_category_id' => 1120359000002670033,
                'category_name' => 'Grade 11',
            ],
            [
                'old_category_id' => 154000200867,
                'zoho_category_id' => 1120359000002670044,
                'category_name' => 'Grade 12',
            ],
        ];

        DB::table('category_mappings')->insert($mappings);
    }
}
