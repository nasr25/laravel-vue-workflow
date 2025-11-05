<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

class HierarchicalDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing departments (SQLite compatible)
        DB::statement('PRAGMA foreign_keys = OFF;');
        Department::query()->delete();
        DB::statement('PRAGMA foreign_keys = ON;');

        // Root: CEO Office
        $ceo = Department::create([
            'name' => 'CEO Office',
            'description' => 'Executive Management',
            'approval_order' => 1,
            'is_active' => true,
            'parent_id' => null
        ]);

        // Level 1: Main Divisions
        $operations = Department::create([
            'name' => 'Operations Division',
            'description' => 'Operations and Production',
            'approval_order' => 2,
            'is_active' => true,
            'parent_id' => $ceo->id
        ]);

        $finance = Department::create([
            'name' => 'Finance Division',
            'description' => 'Financial Management',
            'approval_order' => 3,
            'is_active' => true,
            'parent_id' => $ceo->id
        ]);

        $hr = Department::create([
            'name' => 'HR Division',
            'description' => 'Human Resources',
            'approval_order' => 4,
            'is_active' => true,
            'parent_id' => $ceo->id
        ]);

        $it = Department::create([
            'name' => 'IT Division',
            'description' => 'Information Technology',
            'approval_order' => 5,
            'is_active' => true,
            'parent_id' => $ceo->id
        ]);

        // Level 2: Operations Sub-departments
        $production = Department::create([
            'name' => 'Production',
            'description' => 'Manufacturing and Production',
            'approval_order' => 6,
            'is_active' => true,
            'parent_id' => $operations->id
        ]);

        $quality = Department::create([
            'name' => 'Quality Assurance',
            'description' => 'Quality Control',
            'approval_order' => 7,
            'is_active' => true,
            'parent_id' => $operations->id
        ]);

        $logistics = Department::create([
            'name' => 'Logistics',
            'description' => 'Supply Chain and Distribution',
            'approval_order' => 8,
            'is_active' => true,
            'parent_id' => $operations->id
        ]);

        // Level 2: Finance Sub-departments
        $accounting = Department::create([
            'name' => 'Accounting',
            'description' => 'Financial Accounting',
            'approval_order' => 9,
            'is_active' => true,
            'parent_id' => $finance->id
        ]);

        $treasury = Department::create([
            'name' => 'Treasury',
            'description' => 'Cash Management',
            'approval_order' => 10,
            'is_active' => true,
            'parent_id' => $finance->id
        ]);

        // Level 2: HR Sub-departments
        $recruitment = Department::create([
            'name' => 'Recruitment',
            'description' => 'Talent Acquisition',
            'approval_order' => 11,
            'is_active' => true,
            'parent_id' => $hr->id
        ]);

        $training = Department::create([
            'name' => 'Training & Development',
            'description' => 'Employee Development',
            'approval_order' => 12,
            'is_active' => true,
            'parent_id' => $hr->id
        ]);

        // Level 2: IT Sub-departments
        $development = Department::create([
            'name' => 'Development',
            'description' => 'Software Development',
            'approval_order' => 13,
            'is_active' => true,
            'parent_id' => $it->id
        ]);

        $infrastructure = Department::create([
            'name' => 'Infrastructure',
            'description' => 'IT Infrastructure',
            'approval_order' => 14,
            'is_active' => true,
            'parent_id' => $it->id
        ]);

        // Level 3: Production Sub-departments
        Department::create([
            'name' => 'Assembly Line 1',
            'description' => 'Production Line 1',
            'approval_order' => 15,
            'is_active' => true,
            'parent_id' => $production->id
        ]);

        Department::create([
            'name' => 'Assembly Line 2',
            'description' => 'Production Line 2',
            'approval_order' => 16,
            'is_active' => true,
            'parent_id' => $production->id
        ]);

        // Level 3: Quality Sub-departments
        Department::create([
            'name' => 'Testing Lab',
            'description' => 'Product Testing',
            'approval_order' => 17,
            'is_active' => true,
            'parent_id' => $quality->id
        ]);

        Department::create([
            'name' => 'Inspection',
            'description' => 'Quality Inspection',
            'approval_order' => 18,
            'is_active' => true,
            'parent_id' => $quality->id
        ]);

        // Level 3: Development Sub-departments
        Department::create([
            'name' => 'Frontend Team',
            'description' => 'Frontend Development',
            'approval_order' => 19,
            'is_active' => true,
            'parent_id' => $development->id
        ]);

        Department::create([
            'name' => 'Backend Team',
            'description' => 'Backend Development',
            'approval_order' => 20,
            'is_active' => true,
            'parent_id' => $development->id
        ]);

        Department::create([
            'name' => 'Mobile Team',
            'description' => 'Mobile App Development',
            'approval_order' => 21,
            'is_active' => true,
            'parent_id' => $development->id
        ]);

        $this->command->info('✅ Hierarchical departments created successfully!');
    }
}
