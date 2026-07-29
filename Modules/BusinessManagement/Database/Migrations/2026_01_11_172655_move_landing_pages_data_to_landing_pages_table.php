<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        $introSectionSettings = DB::table('business_settings')
            ->where('settings_type', 'landing_pages_settings')
            ->whereIn('key_name', ['intro_section', 'intro_section_image'])
            ->get()
            ->keyBy('key_name');

        if (!$introSectionSettings->isEmpty()) {
            $introSection = isset($introSectionSettings['intro_section'])
                ? json_decode($introSectionSettings['intro_section']->value, true)
                : [];
            $introImage = isset($introSectionSettings['intro_section_image'])
                ? json_decode($introSectionSettings['intro_section_image']->value, true)
                : [];
            $introBackgroundImage = null;

            if (!empty($introImage['background_image'])) {
                $introBackgroundImage = $introImage['background_image'];
            }

            $introMergedValue = array_merge($introSection, ['background_image' => $introBackgroundImage]);

            DB::table('landing_page_sections')->insert([
                'key_name' => INTRO_CONTENTS,
                'value' => json_encode($introMergedValue),
                'settings_type' => INTRO_SECTION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        $businessStatisticsSettings = DB::table('business_settings')
            ->where('key_name', BUSINESS_STATISTICS)
            ->first();

        if ($businessStatisticsSettings) {
            $businessStatisticsArray = json_decode($businessStatisticsSettings->value, true);

            foreach ($businessStatisticsArray as $key => &$item)
            {
                if (array_key_exists('count', $item))
                {
                    $item['title'] = $item['count'];
                    unset($item['count']);
                }
                $item['status'] = 1;
                DB::table('landing_page_sections')->insert([
                    'key_name' => $key,
                    'value' => json_encode($item),
                    'settings_type' => BUSINESS_STATISTICS,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            }
            unset($item);
        }

         $ourSolutionsIntro = DB::table('business_settings')
            ->where('key_name', OUR_SOLUTIONS_SECTION)
            ->first();

        if ($ourSolutionsIntro) {
            DB::table('landing_page_sections')->insert([
                'key_name' => INTRO_CONTENTS,
                'value' => $ourSolutionsIntro->value,
                'settings_type' => OUR_SOLUTIONS_SECTION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        $solutions = DB::table('business_settings')
            ->where('key_name', OUR_SOLUTIONS_DATA)
            ->get();

        if ($solutions) {
            foreach ($solutions as $ourSolution)
            {
                DB::table('landing_page_sections')->insert([
                    'key_name' => SOLUTIONS,
                    'value' => $ourSolution->value,
                    'settings_type' => OUR_SOLUTIONS_SECTION,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }



        $ourServices = [];

        for ($i = 1; $i < 4; $i++)
        {
            $ourServices[] = [
                'key_name' => 'service_' . $i,
                'value' => json_encode([
                    'tab_name' => '',
                    'title' => '',
                    'description' => '',
                    'status' => '',
                    'image' => ''
                ]),
                'settings_type' => OUR_SERVICES,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('landing_page_sections')->insert($ourServices);

        $galleryContents = [];

        for ($i = 1; $i < 3; $i++)
        {
            $galleryContents[] = [
                'key_name' => 'card_' . $i,
                'value' => json_encode([
                    'title' => '',
                    'subtitle' => '',
                    'image' => ''
                ]),
                'settings_type' => GALLERY,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('landing_page_sections')->insert($galleryContents);

        $earnMoneySettings = DB::table('business_settings')
            ->where('settings_type', 'landing_pages_settings')
            ->whereIn('key_name', ['earn_money', 'earn_money_image'])
            ->get()
            ->keyBy('key_name');

        if (!$earnMoneySettings->isEmpty()) {
            $earnMoneySection = isset($earnMoneySettings['earn_money'])
                ? json_decode($earnMoneySettings['earn_money']->value, true)
                : [];
            $Image = isset($earnMoneySettings['earn_money_image'])
                ? json_decode($earnMoneySettings['earn_money_image']->value, true)
                : [];
            $earnMoneyImage = null;

            if (!empty($Image['image'])) {
                $earnMoneyImage = $Image['image'];
            }

            $earnMoneyMergedValue = array_merge($earnMoneySection, ['image' => $earnMoneyImage]);
            $earnMoneyMergedValue['subtitle'] = $earnMoneyMergedValue['sub_title'];
            unset($earnMoneyMergedValue['sub_title']);

            DB::table('landing_page_sections')->insert([
                'key_name' => INTRO_CONTENTS,
                'value' => json_encode($earnMoneyMergedValue),
                'settings_type' => EARN_MONEY,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $testimonials = DB::table('business_settings')
            ->where('key_name', TESTIMONIAL)
            ->get();

        if ($testimonials) {
            foreach ($testimonials as $testimonial)
            {
                DB::table('landing_page_sections')->insert([
                    'key_name' => 'reviews',
                    'value' => $testimonial->value,
                    'settings_type' => TESTIMONIAL,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        DB::table('landing_page_sections')->whereIn('settings_type', [INTRO_SECTION, BUSINESS_STATISTICS, OUR_SOLUTIONS_SECTION, OUR_SERVICES, GALLERY, EARN_MONEY, TESTIMONIAL])->delete();
    }
};
