<?php

namespace Modules\BusinessManagement\Service;

use App\Http\Controllers\ParcelTrackingController;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\BusinessManagement\Repository\LandingPageSectionRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class LandingPageSectionService extends BaseService implements Interfaces\LandingPageSectionServiceInterface
{
    protected $landingPageSectionRepository;

    public function __construct(LandingPageSectionRepositoryInterface $landingPageSectionRepository)
    {
        parent::__construct($landingPageSectionRepository);
        $this->landingPageSectionRepository = $landingPageSectionRepository;
    }

    public function storeLandingPageIntroSection(array $data): void
    {
        $introSection = $this->landingPageSectionRepository->findOneBy(criteria: [
            'key_name' => $data['key_name'],
        ]);
        $value = ['title' => $data['title'], 'sub_title' => $data['sub_title'], 'background_image' => $introSection->value['background_image'] ?? ''];

        if (array_key_exists('background_image', $data)) {
            $fileName = fileUploader('business/landing-pages/intro-section/', APPLICATION_IMAGE_FORMAT, $data['background_image'], $introSection->value['background_image'] ?? '');
            $value['background_image'] = $fileName;
        }

        if ($introSection)
        {
            $this->landingPageSectionRepository->update(id: $introSection->id, data: ['key_name' => $data['key_name'], 'settings_type' => INTRO_SECTION, 'value' => $value]);
        } else {
            $this->landingPageSectionRepository->create(data: ['key_name' => $data['key_name'], 'settings_type' => INTRO_SECTION, 'value' => $value]);
        }
    }

    public function storeLandingPageBusinessStatistics(array $data): void
    {
        $businessStatistic = $this->landingPageSectionRepository->findOneBy(criteria: [
            'key_name' => $data['key_name'],
        ]);

        $value = ['title' => $data['title'], 'content' => $data['content'], 'image' => $businessStatistic->value['image'] ?? '', 'status' => array_key_exists('status', $data) ? 1 : 0];


        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/landing-pages/business-statistics/' . str_replace('_', '-', $businessStatistic->key_name) . '/', APPLICATION_IMAGE_FORMAT, $data['image'], $businessStatistic->value['image'] ?? '');
            $value['image'] = $fileName;
        }


        if ($businessStatistic) {
            $this->landingPageSectionRepository->update(id: $businessStatistic->id, data: ['key_name' => $businessStatistic->key_name, 'settings_type' => BUSINESS_STATISTICS, 'value' => $value]);
        } else {
            $this->landingPageSectionRepository->create(data: ['key_name' => $businessStatistic->key_name, 'settings_type' => BUSINESS_STATISTICS, 'value' => $value]);
        }
    }

    public function storeLandingPageOurSolutionsSection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => OUR_SOLUTIONS_SECTION];
        if (array_key_exists('id', $data)) $criteria['id'] = $data['id'];

        $ourSolutionsSection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);
        if ($data['key_name'] === INTRO_CONTENTS) $value = ['title' => $data['title'], 'sub_title' => $data['sub_title']];

        if ($data['key_name'] === SOLUTIONS) {
            $value = ['title' => $data['title'], 'description' => $data['description'], 'status' => $ourSolutionsSection?->value['status'] ?? 1];

            if (array_key_exists('image', $data)) {
                $fileName = fileUploader('business/landing-pages/our-solutions/', APPLICATION_IMAGE_FORMAT, $data['image'], isset($data['id']) ? $ourSolutionsSection->value['image'] : '');
                $value['image'] = $fileName;
            } else {
                $value['image'] = $ourSolutionsSection->value['image'] ?? '';
            }
        }

        $solutionData =  ['key_name' => $data['key_name'], 'settings_type' => OUR_SOLUTIONS_SECTION, 'value' => $value];
        $shouldCreate =
            !$ourSolutionsSection ||
            ($data['key_name'] === SOLUTIONS && !isset($data['id']));

        if ($shouldCreate) {
            $this->landingPageSectionRepository->create(data: $solutionData);
        } else {
            $this->landingPageSectionRepository->update(
                id: $ourSolutionsSection->id,
                data: $solutionData
            );
        }
    }

    public function statusChangeOurSolutions(string|int $id, array $data): ?Model
    {
        $attributes = ['id' => $id, 'key_name' => SOLUTIONS, 'settings_type' => OUR_SOLUTIONS_SECTION];
        $ourSolutions = $this->landingPageSectionRepository->findOneBy(criteria: $attributes);
        $value = [
            'title' => $ourSolutions?->value['title'],
            'description' => $ourSolutions?->value['description'],
            'image' => $ourSolutions?->value['image'] ?? '',
            'status' => $data['status'] == 0 ? $data['status'] : 1
        ];
        return $this->landingPageSectionRepository->update(id: $ourSolutions->id, data: ['key_name' => SOLUTIONS, 'settings_type' => OUR_SOLUTIONS_SECTION, 'value' => $value]);
    }

    public function deleteOurSolutions(string|int $id): bool
    {
        $attributes = ['id' => $id, 'key_name' => SOLUTIONS, 'settings_type' => OUR_SOLUTIONS_SECTION];
        $ourSolutions = $this->landingPageSectionRepository->findOneBy(criteria: $attributes);
        $image = $ourSolutions?->value['image'] ?? '';
        if ($image) {
            fileRemover('business/landing-pages/our-solutions/', $image);
        }

        return $this->landingPageSectionRepository->delete(id: $id);
    }

    public function storeLandingPageOurServicesSection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => OUR_SERVICES];

        $ourServicesSection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);
        if ($data['key_name'] === INTRO_CONTENTS) {
            $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];
        } else
        {
            $value = ['tab_name' => $data['tab_name'] ,'title' => $data['title'], 'description' => $data['description'], 'status' => array_key_exists('status', $data) ? 1 : 0];

            if (array_key_exists('image', $data)) {
                $fileName = fileUploader('business/landing-pages/our-services/', APPLICATION_IMAGE_FORMAT, $data['image'], $ourServicesSection->value['image'] ?? '');
                $value['image'] = $fileName;
            } else {
                $value['image'] = $ourServicesSection->value['image'] ?? '';
            }
        }

        $serviceData =  ['key_name' => $data['key_name'], 'settings_type' => OUR_SERVICES, 'value' => $value];

        if ($ourServicesSection) {
            $this->landingPageSectionRepository->update(id: $ourServicesSection->id, data: $serviceData);
        } else {
            $this->landingPageSectionRepository->create(data: $serviceData);
        }
    }

    public function storeLandingPageGallerySection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => GALLERY];

        $gallerySection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);

        $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];

        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/landing-pages/gallery/', APPLICATION_IMAGE_FORMAT, $data['image'], $gallerySection->value['image'] ?? '');
            $value['image'] = $fileName;
        } else {
            $value['image'] = $gallerySection->value['image'] ?? '';
        }


        $galleryData =  ['key_name' => $data['key_name'], 'settings_type' => GALLERY, 'value' => $value];

        if ($gallerySection) {
            $this->landingPageSectionRepository->update(id: $gallerySection->id, data: $galleryData);
        } else {
            $this->landingPageSectionRepository->create(data: $galleryData);
        }
    }

    public function storeLandingPageCustomerAppDownloadSection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => CUSTOMER_APP_DOWNLOAD];

        $customerAppDownloadSection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);

        $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];

        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/landing-pages/customer-app-download/', APPLICATION_IMAGE_FORMAT, $data['image'], $customerAppDownloadSection->value['image'] ?? '');
            $value['image'] = $fileName;
        } else {
            $value['image'] = $customerAppDownloadSection->value['image'] ?? '';
        }

        $customerAppDownloadData =  ['key_name' => $data['key_name'], 'settings_type' => CUSTOMER_APP_DOWNLOAD, 'value' => $value];

        if ($customerAppDownloadSection) {
            $this->landingPageSectionRepository->update(id: $customerAppDownloadSection->id, data: $customerAppDownloadData);
        } else {
            $this->landingPageSectionRepository->create(data: $customerAppDownloadData);
        }
    }

    public function storeLandingPageEarnMoneySection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => EARN_MONEY];

        $earnMoneySection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);

        $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];

        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('business/landing-pages/earn-money/', APPLICATION_IMAGE_FORMAT, $data['image'], $earnMoneySection->value['image'] ?? '');
            $value['image'] = $fileName;
        } else {
            $value['image'] = $earnMoneySection->value['image'] ?? '';
        }

        $earnMoneyData =  ['key_name' => $data['key_name'], 'settings_type' => EARN_MONEY, 'value' => $value];

        if ($earnMoneySection) {
            $this->landingPageSectionRepository->update(id: $earnMoneySection->id, data: $earnMoneyData);
        } else {
            $this->landingPageSectionRepository->create(data: $earnMoneyData);
        }
    }

    public function storeLandingPageTestimonial(array $data): void
    {
        $value = [];
        $value['reviewer_name'] = $data['reviewer_name'];
        $value['designation'] = $data['designation'];
        $value['rating'] = $data['rating'];
        $value['review'] = $data['review'];
        $value['status'] = "1";

        if (array_key_exists('id', $data)) {
            $attributes = ['id' => $data['id'], 'key_name' => 'reviews', 'settings_type' => TESTIMONIAL];
            $testimonial = $this->landingPageSectionRepository->findOneBy(criteria: $attributes);
        }

        if (array_key_exists('reviewer_image', $data)) {
            $fileName = fileUploader('business/landing-pages/testimonial/', $data['reviewer_image']->extension(), $data['reviewer_image'], (array_key_exists('id', $data) && $testimonial?->value['reviewer_image'] ? $testimonial?->value['reviewer_image'] : ''));
            $value['reviewer_image'] = $fileName;
        }
        if (array_key_exists('id', $data)) {
            if (!array_key_exists('reviewer_image', $data)) {
                $value['reviewer_image'] = $testimonial->value['reviewer_image'] ?? '';
            }
            $this->landingPageSectionRepository->update(id: $data['id'], data: ['key_name' => 'reviews', 'settings_type' => TESTIMONIAL, 'value' => $value]);

        } else {
            $this->landingPageSectionRepository->create(data: ['key_name' => 'reviews', 'settings_type' => TESTIMONIAL, 'value' => $value]);
        }
    }
    public function statusChange(string|int $id, array $data): ?Model
    {
        $attributes = ['id' => $id, 'key_name' => 'reviews', 'settings_type' => TESTIMONIAL];
        $testimonial = $this->landingPageSectionRepository->findOneBy(criteria: $attributes);
        $value = [];
        $value['reviewer_name'] = $testimonial?->value['reviewer_name'];
        $value['designation'] = $testimonial?->value['designation'];
        $value['rating'] = $testimonial?->value['rating'];
        $value['review'] = $testimonial?->value['review'];
        $value['reviewer_image'] = $testimonial?->value['reviewer_image'] ?? '';
        $value['status'] = $data['status'] == "0" ? $data['status'] : "1";
        return $this->landingPageSectionRepository->update(id: $id, data: ['key_name' => 'reviews', 'settings_type' => TESTIMONIAL, 'value' => $value]);
    }
    public function storeLandingNewsletterSection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => NEWSLETTER];

        $newsletterSection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);

        $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];

        if (array_key_exists('background_image', $data)) {
            $fileName = fileUploader('business/landing-pages/newsletter/', APPLICATION_IMAGE_FORMAT, $data['background_image'], $newsletterSection->value['background_image'] ?? '');
            $value['background_image'] = $fileName;
        } else {
            $value['background_image'] = $newsletterSection->value['background_image'] ?? '';
        }


        $newsletterData =  ['key_name' => $data['key_name'], 'settings_type' => NEWSLETTER, 'value' => $value];

        if ($newsletterSection) {
            $this->landingPageSectionRepository->update(id: $newsletterSection->id, data: $newsletterData);
        } else {
            $this->landingPageSectionRepository->create(data: $newsletterData);
        }
    }
    public function storeLandingFooterSection(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => FOOTER];

        $footerSection = $this->landingPageSectionRepository->findOneBy(criteria: $criteria);

        $value = ['title' => $data['title']];

        $footerData =  ['key_name' => $data['key_name'], 'settings_type' => FOOTER, 'value' => $value];

        if ($footerSection) {
            $this->landingPageSectionRepository->update(id: $footerSection->id, data: $footerData);
        } else {
            $this->landingPageSectionRepository->create(data: $footerData);
        }
    }

}
