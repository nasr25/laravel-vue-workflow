<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IdeaTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ideaTypes = [
            [
                'name' => 'Process Improvement',
                'name_ar' => 'تحسين العملية',
                'description' => 'Ideas that improve existing processes and workflows',
                'description_ar' => 'أفكار لتحسين العمليات وسير العمل الحالية',
                'color' => '#3b82f6',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Cost Reduction',
                'name_ar' => 'خفض التكاليف',
                'description' => 'Ideas that reduce operational costs and expenses',
                'description_ar' => 'أفكار لتقليل التكاليف والنفقات التشغيلية',
                'color' => '#10b981',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Innovation',
                'name_ar' => 'الابتكار',
                'description' => 'New and innovative ideas that add value',
                'description_ar' => 'أفكار جديدة ومبتكرة تضيف قيمة',
                'color' => '#8b5cf6',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Quality Enhancement',
                'name_ar' => 'تحسين الجودة',
                'description' => 'Ideas that improve product or service quality',
                'description_ar' => 'أفكار لتحسين جودة المنتج أو الخدمة',
                'color' => '#f59e0b',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'Safety & Security',
                'name_ar' => 'الأمن والسلامة',
                'description' => 'Ideas related to workplace safety and security',
                'description_ar' => 'أفكار متعلقة بأمن وسلامة مكان العمل',
                'color' => '#ef4444',
                'is_active' => true,
                'order' => 5,
            ],
        ];

        foreach ($ideaTypes as $ideaType) {
            \App\Models\IdeaType::updateOrCreate(
                ['name' => $ideaType['name']],
                $ideaType
            );
        }
    }
}
