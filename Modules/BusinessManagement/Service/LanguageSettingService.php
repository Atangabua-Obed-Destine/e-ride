<?php

namespace Modules\BusinessManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\BusinessManagement\Repository\BusinessSettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\LanguageSettingServiceInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LanguageSettingService extends BaseService implements Interfaces\LanguageSettingServiceInterface
{
    protected $businessSettingRepository;

    public function __construct(BusinessSettingRepositoryInterface $businessSettingRepository)
    {
        parent::__construct($businessSettingRepository);
        $this->businessSettingRepository = $businessSettingRepository;
    }

    public function storeLanguage(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);

        $lang_array = [];
        $codes = [];
        foreach ($language['value'] as $key => $singleData) {
            if (!array_key_exists('default', $singleData)) {
                $default = array('default' => $singleData['code'] == 'en');
                $singleData = array_merge($singleData, $default);
            }
            $lang_array[] = $singleData;
            $codes[] = $singleData['code'];
        }
        $codes[] = $data['code'];

        if (!file_exists(base_path('resources/lang/' . $data['code']))) {
            mkdir(base_path('resources/lang/' . $data['code']), 0777, true);
        }

        $lang_file = fopen(base_path('resources/lang/' . $data['code'] . '/' . 'lang.php'), "w") or die("Unable to open file!");
        $read = file_get_contents(base_path('resources/lang/en/lang.php'));
        fwrite($lang_file, $read);

        $lang_array[] = [
            'id' => count($language['value']) + 1,
            'code' => $data['code'],
            'direction' => $data['direction'],
            'status' => 0,
            'default' => false,
        ];
        DB::beginTransaction();
        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);

//        $languageInfo = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => 'language', 'settings_type' => BUSINESS_INFORMATION]);
//        $this->businessSettingRepository->update(id: $languageInfo->id, data: [
//            'key_name' => 'language',
//            'value' => $codes,
//            'settings_type' => BUSINESS_INFORMATION
//
//        ]);
        DB::commit();
    }

    public function updateLanguage(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $singleLanguage) {
            $lang = [
                'id' => $singleLanguage['id'],
                'direction' => $singleLanguage['code'] == $data['code'] ? $data['direction'] : $singleLanguage['direction'],
                'code' => $singleLanguage['code'],
                'status' => $singleLanguage['status'],
                'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
            ];
            $lang_array[] = $lang;
        }

        $attributes = [
            'key_name' => SYSTEM_LANGUAGE,
            'settings_type' => LANGUAGE_SETTINGS,
            'value' => $lang_array
        ];
        $this->businessSettingRepository->update(id: $language['id'], data: $attributes);
    }

    public function deleteLanguage($lang)
    {
        DB::beginTransaction();
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $del_default = false;
        $lang_array = [];
        foreach ($language['value'] as $data) {
            if ($data['code'] != $lang) {
                $lang_data = [
                    'id' => $data['id'],
                    'direction' => $data['direction'] ?? 'ltr',
                    'code' => $data['code'],
                    'status' => ($del_default && $data['code'] == 'en') ? 1 : $data['status'],
                    'default' => ($del_default && $data['code'] == 'en') ? true : (array_key_exists('default', $data) ? $data['default'] : (($data['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang_data;
            }
        }
        $attributes = [
            'key_name' => SYSTEM_LANGUAGE,
            'settings_type' => LANGUAGE_SETTINGS,
            'value' => $lang_array
        ];
        $this->businessSettingRepository->update(id: $language['id'], data: $attributes);

        $dir = base_path('resources/lang/' . $lang);
        if (File::isDirectory($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }

//        $languages = array();
//        $languageInfo = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => 'language', 'settings_type' => BUSINESS_INFORMATION]);
//        foreach ($languageInfo['value'] as $key => $data) {
//            if ($data != $lang) {
//                $languages[] = $data;
//            }
//        }
//        if (in_array('en', $languages)) {
//            unset($languages[array_search('en', $languages)]);
//        }
//        array_unshift($languages, 'en');
//
//        $attributes = [
//            'key_name' => 'language',
//            'settings_type' => BUSINESS_INFORMATION,
//            'value' => $languages
//        ];
//        $this->businessSettingRepository->update(id: $languageInfo->id, data: $attributes);
        DB::commit();
    }

    public function changeLanguageStatus(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $key => $singleLanguage) {
            if ($singleLanguage['code'] == $data['code']) {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'] == 1 ? 0 : 1,
                    'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            } else {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'],
                    'default' => (array_key_exists('default', $singleLanguage) ? $singleLanguage['default'] : (($singleLanguage['code'] == 'en') ? true : false)),
                ];
                $lang_array[] = $lang;
            }
        }
        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);
    }

    public function changeLanguageDefaultStatus(array $data)
    {
        $language = $this->businessSettingRepository->findOneBy(criteria: ['key_name' => SYSTEM_LANGUAGE, 'settings_type' => LANGUAGE_SETTINGS]);
        $lang_array = [];
        foreach ($language['value'] as $key => $singleLanguage) {
            if ($singleLanguage['code'] == $data['code']) {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => 1,
                    'default' => true,
                ];
                $lang_array[] = $lang;
                session()->put('locale', $singleLanguage['code']);
                session()->put('direction', $singleLanguage['direction'] ?? 'ltr');
            } else {
                $lang = [
                    'id' => $singleLanguage['id'],
                    'direction' => $singleLanguage['direction'] ?? 'ltr',
                    'code' => $singleLanguage['code'],
                    'status' => $singleLanguage['status'],
                    'default' => false,
                ];
                $lang_array[] = $lang;
            }
        }

        $this->businessSettingRepository->update(id: $language['id'], data: [
            'key_name' => SYSTEM_LANGUAGE,
            'value' => $lang_array,
            'settings_type' => LANGUAGE_SETTINGS
        ]);
    }

    public function translate($lang, $data)
    {
        $fullData = include(base_path('resources/lang/' . $lang . '/lang.php'));
        $fullData = array_filter($fullData, fn($value) => !is_null($value) && $value !== '');

        if (array_key_exists('search', $data)) {
            $searchTerm = $data['search'];
            $fullData = array_filter($fullData, function ($value, $key) use ($searchTerm) {
                return (stripos($value, $searchTerm) !== false) || (stripos(ucfirst(str_replace('_', ' ', removeInvalidCharacters($key))), $searchTerm) !== false);
            }, ARRAY_FILTER_USE_BOTH);
        }
        ksort($fullData);

        return $this->convertArrayToCollection($lang, $fullData, paginationLimit());
    }

    public function storeTranslate(array $data, $lang)
    {
        $translateData = include(base_path('resources/lang/' . $lang . '/lang.php'));
        $translateData[$data['key']] = $data['value'];
        $str = "<?php return " . var_export($translateData, true) . ";";
        file_put_contents(base_path('resources/lang/' . $lang . '/lang.php'), $str);
    }

    public function autoTranslate(array $data, $lang)
    {
        $lang_code = getLanguageCode($lang);
        $translateData = include(base_path('resources/lang/' . $lang . '/lang.php'));
        $translated = autoTranslator(str_replace('_', ' ', $data['key']), 'en', $lang_code);
        $translateData[$data['key']] = $translated;
        $str = "<?php return " . var_export($translateData, true) . ";";
        file_put_contents(base_path('resources/lang/' . $lang . '/lang.php'), $str);
        return $translated;
    }

    public function autoTranslateAll(array $data, $lang)
    {
        $translatingCount = $data['translating_count'] <= 0 ? 1 : $data['translating_count'];
        $langCode = getLanguageCode($lang);

        if (!isset($data['total_start_time'])) {
            $data['total_start_time'] = microtime(true);
            $data['processed_so_far'] = 0;
        }

        $data_filtered = [];
        $data_filtered_2 = [];
        $newMessagesPath = base_path("resources/lang/{$lang}/new-messages.php");
        $count = 0;
        $start_time = now();
        $items_processed = 20;

        if (!file_exists($newMessagesPath)) {
            $str = "<?php return " . var_export($data_filtered, true) . ";";
            file_put_contents($newMessagesPath, $str);
        }

        $translatedData = include($newMessagesPath);
        $fullData = include(base_path("resources/lang/{$lang}/lang.php"));
        $translatedDataCount = count($translatedData);
        if ($translatedDataCount > 0) {
            $textsToTranslate = [];
            $keysMap = [];

            foreach ($translatedData as $key_1 => $data_1) {
                if ($count >= $items_processed) break;

                $translated = str_replace('_', ' ', removeInvalidCharacters($key_1));
                $textsToTranslate[] = $translated;
                $keysMap[] = $key_1;
                $count++;
            }
            $translations = $this->autoTranslatorBatch($textsToTranslate, 'en', $langCode);

            foreach ($translations as $i => $translated) {
                $data_filtered_2[$keysMap[$i]] = $translated;
                unset($translatedData[$keysMap[$i]]);
            }
            $str = "<?php return " . var_export($translatedData, true) . ";";
            file_put_contents($newMessagesPath, $str);

            $merged_data = array_replace($fullData, $data_filtered_2);
            $str = "<?php\n\nreturn " . var_export($merged_data, true) . ";\n";
            file_put_contents(base_path("resources/lang/{$lang}/lang.php"), $str);


            $data['processed_so_far'] += $count;
            $remaining_count = count($translatedData);
            $percentage = $remaining_count > 0 && $translatingCount > 0
                ? max(0, min(100, 100 - (($remaining_count / $translatingCount) * 100)))
                : 100;
            $total_elapsed_seconds = microtime(true) - $data['total_start_time'];
            $rate_per_second = $total_elapsed_seconds > 0
                ? $data['processed_so_far'] / $total_elapsed_seconds
                : 0.01;

            $total_time_needed_seconds = $remaining_count > 0
                ? (int) round($remaining_count / $rate_per_second)
                : 0;

            $hours   = floor($total_time_needed_seconds / 3600);
            $minutes = floor(($total_time_needed_seconds % 3600) / 60);
            $seconds = $total_time_needed_seconds % 60;
            return [
                'status' => 1,
                'translatedDataCount' => $translatedDataCount,
                'percentage' => $percentage,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'hours' => $hours,
                'time_taken' => round($total_elapsed_seconds),
                'renmaining_translated_data_count' => $remaining_count,
                'translated_batch' => $translations,
                'total_start_time' => $data['total_start_time'],
                'processed_so_far' => $data['processed_so_far'],
            ];

        } else {
            foreach ($fullData as $key => $singleData) {
                if (preg_match('/^[\x20-\x7E\x{2019}]+$/u', $singleData)) {
                    $data_filtered[$key] = $singleData;
                    $str = "<?php return " . var_export($data_filtered, true) . ";";
                    file_put_contents($newMessagesPath, $str);
                }
            }

            return [
                'status' => 2,
                'data_filtered' => $data_filtered,
                'total_start_time' => $data['total_start_time'],
                'processed_so_far' => $data['processed_so_far'],
            ];
        }
    }

    private function autoTranslatorBatch(array $texts, $sl, $tl): array
    {
        $placeholdersList = [];
        $batchInput = [];

        foreach ($texts as $q) {
            $placeholders = [];
            $i = 0;
            $tmp = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$placeholders, &$i) {
                $token = '__PH_' . $i . '__';
                $placeholders[$token] = ":" . $m[1];
                $i++;
                return $token;
            }, $q);

            $placeholdersList[] = $placeholders;
            $batchInput[] = $tmp;
        }

        $q = implode("\n", $batchInput);
        $url = "https://translate.googleapis.com/translate_a/single?client=gtx"
            . "&ie=UTF-8&oe=UTF-8&dt=t"
            . "&sl={$sl}&tl={$tl}&hl={$tl}"
            . "&q=" . urlencode($q);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return $texts;
        }

        curl_close($ch);

        $res = json_decode($response, true);
        if (!$res || !isset($res[0])) {
            return $texts;
        }

        $translatedAll = '';
        foreach ($res[0] as $transChunk) {
            $translatedAll .= $transChunk[0];
        }

        $translatedTexts = explode("\n", $translatedAll);

        foreach ($translatedTexts as $idx => &$translated) {
            foreach ($placeholdersList[$idx] as $token => $original) {
                $translated = str_ireplace($token, $original, $translated);
            }
        }

        return $translatedTexts;
    }

    private function convertArrayToCollection($lang, $items, $perPage = null, $page = null, $options = []): LengthAwarePaginator
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        $options = array_merge($options, [
            "path" => route('admin.business.languages.translate', [$lang]),
            "pageName" => "page"
        ]);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
