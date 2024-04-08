<?php

namespace humhub\components;

use humhub\modules\content\models\ContentContainerSetting;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use yii\base\Component;
use Yii;

/**
 * Class CloudflareApi
 * @package humhub\components
 */
class CloudflareApi extends Component
{
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_POST = 'POST';
    const HTTP_METHOD_PUT = 'PUT';
    const HTTP_METHOD_PATCH = 'PATCH';
    const HTTP_METHOD_DELETE = 'DELETE';
    
    /** @var string|null API URL */
    public ?string $apiUrl = 'https://api.cloudflare.com/client/v4/';
    
    /** @var string|null Cloudflare API authentication key */
    public ?string $authKey;
    
    /** @var string|null Cloudflare API authentication email */
    public ?string $authEmail;
    
    /** @var array List of errors */
    protected array $_errors = [];

    /**
     * Get the list of zones
     * @return array
     */
    public function getListZones(): array
    {
        return $this->makeRequest('zones');
    }

    /**
     * Make a request to Cloudflare API
     * @param string $endpoint
     * @param array $data
     * @param string $httpMethod
     * @return array
     */
    private function makeRequest(string $endpoint, array $data = [], string $httpMethod = self::HTTP_METHOD_GET): array
    {
        $url = $this->apiUrl . $endpoint;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_HTTPHEADER => [
                'X-Auth-Email: ' . $this->authEmail,
                'X-Auth-Key: ' . $this->authKey,
                'Content-Type: application/json',
            ],
        ];

        if (!empty($data)) {
            if ($httpMethod === self::HTTP_METHOD_GET) {
                $url .= '?' . http_build_query($data) ?: '';
            } else {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        $ch = curl_init();
        if ($ch === false) {
            return [];
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        if ($response === false) {
            return [];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400 || !empty($responseData['errors'])) {
            $this->addError($responseData['errors'][0]['message'] ?? 'Error occurred.', $httpCode);
        }

        return $responseData ?: [];
    }

    /**
     * Purge cache for a specific zone
     * @param string $zoneId
     * @return array
     */
    public function purgeCache(string $zoneId): array
    {
        return $this->makeRequest("zones/{$zoneId}/purge_cache", ['purge_everything' => true], self::HTTP_METHOD_DELETE);
    }

    /**
     * Add an error
     * @param string $message
     * @param mixed $code
     */
    protected function addError(string $message, mixed $code = ''): void
    {
        $prefix = !empty($code) ? $code . ': ' : '';
        $this->_errors[] = $prefix . $message;
    }

    /**
     * Get errors
     * @param bool $flush
     * @return array
     */
    public function getErrors(bool $flush = false): array
    {
        $errors = $this->_errors;
        if ($flush) {
            $this->_errors = [];
        }
        return $errors;
    }

    /**
     * Check if there are errors
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->_errors);
    }

    /**
     * Purge cache for a specific container (Space, User, ContentContainer)
     * @param Space|User|ContentContainer $container
     * @return array
     */
    public function purgeCacheForContainer($container): array
    {
        $zoneId = $this->getZoneIdForContainer($container);
        if ($zoneId !== null) {
            return $this->purgeCache($zoneId);
        }
        return [];
    }

    /**
     * Get the Cloudflare zone ID for a specific HumHub container
     * @param Space|User|ContentContainer $container
     * @return string|null
     */
    protected function getZoneIdForContainer($container): ?string
    {
        if ($container instanceof Space) {
            // Example: Get zone ID based on space specific settings
            return $this->getZoneIdForSpace($container);
        } elseif ($container instanceof User) {
            // Example: Get zone ID based on user specific settings
            return $this->getZoneIdForUser($container);
        } elseif ($container instanceof ContentContainer) {
            // Example: Get zone ID based on general content container settings
            return $this->getZoneIdForContentContainer($container);
        }
        return null;
    }

    /**
     * Example: Get the Cloudflare zone ID for a specific space
     * @param Space $space
     * @return string|null
     */
    protected function getZoneIdForSpace(Space $space): ?string
    {
        // Retrieve zone ID from space specific settings
        $zoneId = ContentContainerSetting::Get($space->contentContainer, 'cloudflare_zone_id', null);
        return $zoneId !== null ? (string) $zoneId : null;
    }

    /**
     * Example: Get the Cloudflare zone ID for a specific user
     * @param User $user
     * @return string|null
     */
    protected function getZoneIdForUser(User $user): ?string
    {
        // Retrieve zone ID from user specific settings
        $zoneId = Yii::$app->getModule('cloudflare')->settings->user($user)->get('zone_id', null);
        return $zoneId !== null ? (string) $zoneId : null;
    }

    /**
     * Example: Get the Cloudflare zone ID for a generic content container
     * @param ContentContainer $container
     * @return string|null
     */
    protected function getZoneIdForContentContainer(ContentContainer $container): ?string
    {
        // Retrieve zone ID from generic content container settings
        $zoneId = ContentContainerSetting::Get($container, 'cloudflare_zone_id', null);
        return $zoneId !== null ? (string) $zoneId : null;
    }
}
