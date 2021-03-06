<?php
namespace MooPhp\MooInterface\Data\UserData;
use Weasel\JsonMarshaller\Config\DoctrineAnnotations\JsonProperty;
use Weasel\JsonMarshaller\Config\DoctrineAnnotations\JsonTypeName;

/**
 * @package MooPhp
 * @author Jonathan Oddy <jonathan@moo.com>
 * @copyright Copyright (c) 2012, Moo Print Ltd.
 *
 * @JsonTypeName("multiLineTextData")
 */

class MultiLineTextData extends Datum
{

    const ALIGN_LEFT = "left";
    const ALIGN_CENTER = "center";
    const ALIGN_RIGHT = "right";

    /**
     * @var string
     */
    protected $_text;
    /**
     * @var \MooPhp\MooInterface\Data\Types\Font
     */
    protected $_font;
    /**
     * @var float
     */
    protected $_pointSize;
    /**
     * @var \MooPhp\MooInterface\Data\Types\Colour
     */
    protected $_colour;
    /**
     * @var string
     */
    protected $_alignment;

    /**
     * @return string
     * @JsonProperty(type="string")
     */
    public function getAlignment()
    {
        return $this->_alignment;
    }

    /**
     * @return \MooPhp\MooInterface\Data\Types\Colour
     * @JsonProperty(type="\MooPhp\MooInterface\Data\Types\Colour")
     */
    public function getColour()
    {
        return $this->_colour;
    }

    /**
     * @return \MooPhp\MooInterface\Data\Types\Font
     * @JsonProperty(type="\MooPhp\MooInterface\Data\Types\Font")
     */
    public function getFont()
    {
        return $this->_font;
    }

    /**
     * @return float
     * @JsonProperty(type="float")
     */
    public function getPointSize()
    {
        return $this->_pointSize;
    }

    /**
     * @return string
     * @JsonProperty(type="string")
     */
    public function getText()
    {
        return $this->_text;
    }

    public function __construct($linkId = null)
    {
        parent::__construct($linkId);
    }

    /**
     * @param string $alignment
     * @return \MooPhp\MooInterface\Data\UserData\MultiLineTextData
     * @JsonProperty(type="string")
     */
    public function setAlignment($alignment)
    {
        $this->_alignment = $alignment;
        return $this;
    }

    /**
     * @param \MooPhp\MooInterface\Data\Types\Colour $colour
     * @return \MooPhp\MooInterface\Data\UserData\MultiLineTextData
     * @JsonProperty(type="\MooPhp\MooInterface\Data\Types\Colour")
     */
    public function setColour($colour)
    {
        $this->_colour = $colour;
        return $this;
    }

    /**
     * @JsonProperty(type="\MooPhp\MooInterface\Data\Types\Font")
     * @param \MooPhp\MooInterface\Data\Types\Font $font
     * @return \MooPhp\MooInterface\Data\UserData\MultiLineTextData
     */
    public function setFont($font)
    {
        $this->_font = $font;
        return $this;
    }

    /**
     * @param float $pointSize
     * @return \MooPhp\MooInterface\Data\UserData\MultiLineTextData
     * @JsonProperty(type="float")
     */
    public function setPointSize($pointSize)
    {
        $this->_pointSize = $pointSize;
        return $this;
    }

    /**
     * @param string $text
     * @return \MooPhp\MooInterface\Data\UserData\MultiLineTextData
     * @JsonProperty(type="string")
     */
    public function setText($text)
    {
        $this->_text = $text;
        return $this;
    }

}
