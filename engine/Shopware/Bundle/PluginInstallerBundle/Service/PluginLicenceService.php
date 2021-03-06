<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\PluginInstallerBundle\Service;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\PluginInstallerBundle\Context\UpdateLicencesRequest;
use Shopware\Bundle\PluginInstallerBundle\StoreClient;
use Shopware\Bundle\PluginInstallerBundle\Struct\PluginInformationStruct;
use Shopware\Components\License\Service\LocalLicenseUnpackService;

class PluginLicenceService
{
    const TYPE_UNLICENSED = 99;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var InstallerService
     */
    private $installer;

    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var LocalLicenseUnpackService
     */
    private $unpackService;

    /**
     * @param Connection $connection
     * @param InstallerService $installer
     * @param StoreClient $storeClient
     * @param LocalLicenseUnpackService $unpackService
     */
    public function __construct(
        Connection $connection,
        InstallerService $installer,
        StoreClient $storeClient,
        LocalLicenseUnpackService $unpackService
    ) {
        $this->connection = $connection;
        $this->installer = $installer;
        $this->storeClient = $storeClient;
        $this->unpackService = $unpackService;
    }

    /**
     * @param string $licenceKey
     * @return int
     */
    public function importLicence($licenceKey)
    {
        $persister = new \Shopware_Components_LicensePersister(
            $this->connection
        );

        $info = \Shopware_Components_License::readLicenseInfo($licenceKey);

        if ($info == false) {
            throw new \RuntimeException();
        }

        return $persister->saveLicense($info, true);
    }

    /**
     * @param UpdateLicencesRequest $request
     * @return array
     */
    public function updateLicences(UpdateLicencesRequest $request)
    {
        $response = $this->storeClient->doAuthPostRequestRaw(
            $request->getToken(),
            '/licenseupgrades/simple',
            [
                'domain' => $request->getDomain(),
                'shopwareVersion' => $request->getShopwareVersion(),
                'locale' => $request->getLocale()
            ]
        );

        return $response;
    }

    /**
     * function to get expired and soon expiring plugins
     * @return PluginInformationStruct[]
     */
    public function getExpiringLicenses()
    {
        $expiringPluginLicenses = [];
        $licenses = $this->getLicences();

        if (empty($licenses)) {
            return $expiringPluginLicenses;
        }
        $expirations = $this->getExpirations($licenses);
        foreach ($expirations as $expiration => $license) {
            $expirationDate = new \DateTime($expiration);
            if ($this->isExpired($expirationDate) || $this->isSoonExpiring($expirationDate)) {
                $expiringPluginLicenses[] = $this->createPluginInformationStruct($license);
            }
        }
        return $expiringPluginLicenses;
    }

    /**
     * function to get only expired plugins
     * @return PluginInformationStruct[]
     */
    public function getExpiredLicenses()
    {
        $expiredPluginLicenses = [];
        $licenses = $this->getLicences();

        if (empty($licenses)) {
            return $expiredPluginLicenses;
        }
        $expirations = $this->getExpirations($licenses);
        foreach ($expirations as $expiration => $license) {
            $expirationDate = new \DateTime($expiration);
            if ($this->isExpired($expirationDate)) {
                $expiredPluginLicenses[] = $this->createPluginInformationStruct($license);
            }
        }
        return $expiredPluginLicenses;
    }

    /**
     * @param array $licenses
     * @return array $expirations
     */
    private function getExpirations(array $licenses)
    {
        $expirations = [];
        foreach ($licenses as $license) {
            if (!empty($license['license'])) {
                $info = $this->unpackService->readLicenseInfo(($license['license']));
                if (!$info) {
                    continue;
                }
                $license = array_merge($license, $info);
            }

            if (empty($license['expiration'])) {
                continue;
            }

            $expirations[$license['expiration']] = $license;
        }

        return $expirations;
    }

    /**
     * @param PluginInformationStruct[] $pluginInformation
     * @param string $domain
     */
    public function updateLocalLicenseInformation(array $pluginInformation, $domain)
    {
        $this->cleanupLocalLicenseInformation();
        foreach ($pluginInformation as $plugin) {
            if ($plugin->getLicenseExpiration() == null && !$plugin->isUnknownLicense()) {
                continue;
            }
            $license = $this->getLocalLicenseByPluginName($plugin->getTechnicalName());
            if (empty($license)) {
                $this->createLocalLicenseInformation($plugin, $domain);
            } elseif (empty($license['license'])) {
                $this->updateLocalLicenseExpirationInformation($license, $plugin);
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    private function cleanupLocalLicenseInformation()
    {
        $this->connection->delete('s_core_licenses', ['license' => '']);
    }

    /**
     * @param string $pluginName
     * @return array
     */
    private function getLocalLicenseByPluginName($pluginName)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('license.*')
            ->from('s_core_licenses', 'license')
            ->where('license.module = :plugin')
            ->setParameter('plugin', $pluginName);

        return $queryBuilder->execute()->fetch();
    }

    /**
     * @param PluginInformationStruct $plugin
     * @param string $domain
     */
    private function createLocalLicenseInformation(PluginInformationStruct $plugin, $domain)
    {
        $today = new \DateTime();
        $data = [
            'module' => $plugin->getTechnicalName(),
            'host' => $domain,
            'label' => $plugin->getLabel(),
            'license' => '',
            'version' => $plugin->getVersion(),
            'active' => 1,
            'source' => $plugin->getSource(),
            'added' => $today->format('Y-m-d')
        ];

        if ($plugin->isUnknownLicense()) {
            $type = self::TYPE_UNLICENSED;
        } else {
            $creationDate = new \DateTime($plugin->getLicenseCreation());
            $expirationDate = new \DateTime($plugin->getLicenseExpiration());
            $type = $plugin->getType();
            $data['creation'] = $creationDate->format('Y-m-d');
            $data['expiration'] = $expirationDate->format('Y-m-d');
        }
        $data['type'] = $type;

        $this->connection->insert('s_core_licenses', $data);
    }

    /**
     * @param array $license
     * @param PluginInformationStruct $plugin
     */
    private function updateLocalLicenseExpirationInformation(array $license, PluginInformationStruct $plugin)
    {
        $expirationDate = $plugin->getLicenseExpiration();
        if ($expirationDate !== $license['expiration']) {
            $this->connection->update('s_core_licenses', ['expiration' => $expirationDate], ['id' => $license['id']]);
        }
    }

    /**
     * function to get all plugin licenses of active plugins
     * @return array
     */
    private function getLicences()
    {
        /**@var $connection Connection */
        $connection = $this->connection;
        $builder = $connection->createQueryBuilder();

        $builder->select(['license.module, license.label, license.expiration, license.license'])
            ->from('s_core_licenses', 'license')
            ->leftJoin('license', 's_core_plugins', 'plugin', 'plugin.name = license.module')
            ->where('plugin.active = 1');

        $builderExecute = $builder->execute();
        return $builderExecute->fetchAll();
    }

    /**
     * @param \DateTime $expirationDate
     * @return boolean
     */
    private function isExpired(\DateTime $expirationDate)
    {
        $diff = $expirationDate->diff(new \DateTime('now'));

        return $diff->invert == 0;
    }

    /**
     * @param \DateTime $expirationDate
     * @param int $daysTillExpiration
     * @return boolean
     */
    private function isSoonExpiring(\DateTime $expirationDate, $daysTillExpiration = 14)
    {
        $diff = $expirationDate->diff(new \DateTime('now'));

        return $diff->invert == 1 && $diff->days <= $daysTillExpiration;
    }

    /**
     * @param array $data
     * @return PluginInformationStruct
     */
    private function createPluginInformationStruct(array $data)
    {
        $information = [
            'label' => $data['label'],
            'name' => $data['module'],
            'licenseExpiration' => $data['expiration']
        ];

        return new PluginInformationStruct($information);
    }
}
