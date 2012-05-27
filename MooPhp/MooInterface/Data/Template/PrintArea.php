<?php
namespace MooPhp\MooInterface\Data\Template;
use PhpXmlMarshaller\Config\Annotations\XmlElement;
use PhpXmlMarshaller\Config\Annotations\XmlAttribute;
use PhpXmlMarshaller\Config\Annotations\XmlRootElement;
/**
 * @package MooPhp
 * @author Jonathan Oddy <jonathan at woaf.net>
 * @copyright Copyright (c) 2011, Jonathan Oddy
 *
 * @XmlRootElement(namespace="http://www.moo.com/xsd/template-1.0")
 */

class PrintArea {

	/**
	 * @var float
	 */
	protected $_height;

	/**
	 * @var float
	 */
	protected $_width;

	/**
	 * @return float
	 */
	public function getHeight() {
		return $this->_height;
	}

	/**
	 * @return float
	 */
	public function getWidth() {
		return $this->_width;
	}

	/**
	 * @param float $height
     * @XmlElement(type="float")
	 */
	public function setHeight($height) {
		$this->_height = $height;
	}

	/**
	 * @param float $width
     * @XmlElement(type="float")
	 */
	public function setWidth($width) {
		$this->_width = $width;
	}

}
