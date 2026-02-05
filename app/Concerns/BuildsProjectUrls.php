<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Domain\Project\ValueObjects\ProjectConfigurationVO;

/**
 * Trait for building project URLs based on selected services.
 */
trait BuildsProjectUrls
{
    /**
     * Build project URLs based on selected services.
     *
     * @return array<string, string>
     */
    protected function buildProjectUrls(ProjectConfigurationVO $config, string $projectDomain): array
    {
        $urls = [
            'Application' => "https://{$projectDomain}",
        ];

        // Get services from current environment
        $currentEnv = $config->rawConfig['environments']['current'] ?? 'dev';
        $services = $config->rawConfig['environments'][$currentEnv]['services'] ?? [];

        // Check for Horizon
        if (isset($services['workers']) && in_array('horizon', $services['workers'], true)) {
            $urls['Horizon Dashboard'] = "https://{$projectDomain}/horizon";
        }

        // Check for Mailpit
        if (isset($services['mail']) && in_array('mailpit', $services['mail'], true)) {
            $urls['Mailpit'] = "https://mail.{$projectDomain}";
        }

        // Check for Meilisearch
        if (isset($services['search']) && in_array('meilisearch', $services['search'], true)) {
            $urls['Meilisearch'] = "https://search.{$projectDomain}";
        }

        // Check for Typesense
        if (isset($services['search']) && in_array('typesense', $services['search'], true)) {
            $urls['Typesense'] = "https://search.{$projectDomain}";
        }

        // Check for MinIO
        if (isset($services['storage']) && in_array('minio', $services['storage'], true)) {
            $urls['MinIO Console'] = "https://minio.{$projectDomain}";
        }

        // Always add Traefik Dashboard
        $urls['Traefik Dashboard'] = 'https://traefik.local.test';

        return $urls;
    }

    /**
     * Build project URLs from an array of selected services (e.g., during installation).
     *
     * @param  array<int, string>  $selectedServices
     * @return array<string, string>
     */
    protected function buildProjectUrlsFromServices(array $selectedServices, string $projectDomain): array
    {
        $urls = [
            'Application' => "https://{$projectDomain}",
        ];

        foreach ($selectedServices as $serviceKey) {
            [$category, $serviceName] = explode('.', $serviceKey);

            // Add Horizon dashboard URL
            if ($category === 'workers' && $serviceName === 'horizon') {
                $urls['Horizon Dashboard'] = "https://{$projectDomain}/horizon";
            }

            // Add Mailpit URL
            if ($category === 'mail' && $serviceName === 'mailpit') {
                $urls['Mailpit'] = "https://mail.{$projectDomain}";
            }

            // Add Meilisearch URL
            if ($category === 'search' && $serviceName === 'meilisearch') {
                $urls['Meilisearch'] = "https://search.{$projectDomain}";
            }

            // Add Typesense URL
            if ($category === 'search' && $serviceName === 'typesense') {
                $urls['Typesense'] = "https://search.{$projectDomain}";
            }

            // Add MinIO URL
            if ($category === 'storage' && $serviceName === 'minio') {
                $urls['MinIO Console'] = "https://minio.{$projectDomain}";
            }
        }

        // Always add Traefik Dashboard
        $urls['Traefik Dashboard'] = 'https://traefik.local.test';

        return $urls;
    }
}
