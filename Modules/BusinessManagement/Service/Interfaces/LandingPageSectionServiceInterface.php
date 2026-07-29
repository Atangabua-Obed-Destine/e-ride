<?php

namespace Modules\BusinessManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface LandingPageSectionServiceInterface extends BaseServiceInterface
{
    public function storeLandingPageIntroSection(array $data): void;
    public function storeLandingPageBusinessStatistics(array $data): void;
    public function storeLandingPageOurSolutionsSection(array $data): void;
    public function statusChangeOurSolutions(string|int $id, array $data): ?Model;
    public function deleteOurSolutions(string|int $id): bool;
    public function storeLandingPageOurServicesSection(array $data): void;
    public function storeLandingPageGallerySection(array $data): void;
    public function storeLandingPageCustomerAppDownloadSection(array $data): void;
    public function storeLandingPageEarnMoneySection(array $data): void;
    public function storeLandingPageTestimonial(array $data): void;
    public function statusChange(string|int $id, array $data): ?Model;
    public function storeLandingNewsletterSection(array $data): void;
    public function storeLandingFooterSection(array $data): void;
}
