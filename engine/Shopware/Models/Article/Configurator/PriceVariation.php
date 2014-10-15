<?php
/**
 * Shopware 4
 * Copyright © shopware AG
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

namespace Shopware\Models\Article\Configurator;

use Shopware\Components\Model\ModelEntity,
    Doctrine\ORM\Mapping AS ORM,
    Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 * @ORM\Table(name="s_article_configurator_price_variations")
 */
class PriceVariation extends ModelEntity
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="options", type="string", length=255, nullable=true)
     */
    private $options;

    /**
     * @var float $variation
     *
     * @ORM\Column(name="variation", type="float", nullable=false, precision=3)
     */
    private $variation;

    /**
     * @var \Shopware\Models\Article\Configurator\Set
     * @ORM\ManyToOne(targetEntity="Shopware\Models\Article\Configurator\Set", inversedBy="priceVariations")
     * @ORM\JoinColumn(name="configurator_set_id", referencedColumnName="id")
     */
    protected $configuratorSet;

    /**
     * @var integer
     * @ORM\Column(name="is_gross", type="integer", nullable=false)
     */
    protected $isGross;

    /**
     * @var integer
     * @ORM\Column(name="configurator_set_id", type="integer", nullable=true)
     */
    private $configuratorSetId = null;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return float
     */
    public function getVariation()
    {
        return $this->variation;
    }

    /**
     * @param float $variation
     */
    public function setVariation($variation)
    {
        $this->variation = $variation;
    }

    /**
     * @return \Shopware\Models\Article\Configurator\Set
     */
    public function getConfiguratorSet()
    {
        return $this->configuratorSet;
    }

    /**
     * @param \Shopware\Models\Article\Configurator\Set $configuratorSet
     */
    public function setConfiguratorSet($configuratorSet)
    {
        $this->configuratorSet = $configuratorSet;
    }

    /**
     * @param string $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return int
     */
    public function getIsGross()
    {
        return $this->isGross;
    }

    /**
     * @param int $isGross
     */
    public function setIsGross($isGross)
    {
        $this->isGross = $isGross;
    }
}