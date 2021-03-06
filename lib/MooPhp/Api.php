<?php
namespace MooPhp;

use MooPhp\MooInterface\Client\Image;
use MooPhp\MooInterface\Data\FontSpec;
use MooPhp\MooInterface\Data\ImageBasket;
use MooPhp\MooInterface\Data\Side;
use MooPhp\MooInterface\Request\RenderSide;
use MooPhp\MooInterface\Request\RenderSideUrl;
use MooPhp\MooInterface\Request\Request;
use Weasel\Annotation\AnnotationConfigurator;
use Weasel\JsonMarshaller\JsonMapper;
use Weasel\XmlMarshaller\XmlMapper;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Weasel\WeaselDoctrineAnnotationDrivenFactory;
use Weasel\WeaselFactory;
use MooPhp\MooInterface\Data\PhysicalSpec;

/**
 * @package Api.php
 * @author Jonathan Oddy <jonathan@moo.com>
 * @copyright Copyright (c) 2012, Moo Print Ltd.
 */
class Api implements MooInterface\MooApi, LoggerAwareInterface
{

    /**
     * @var Client\Client
     */
    protected $_client;

    private $_jsonMapper;
    private $_xmlMapper;

    protected $_weaselFactory;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    public function __construct(Client\Client $client = null)
    {
        $this->_client = $client;
        $this->_weaselFactory = new WeaselDoctrineAnnotationDrivenFactory();
    }

    protected function _getJsonMapper()
    {
        if (!isset($this->_jsonMapper)) {
            $this->_jsonMapper = $this->_weaselFactory->getJsonMapperInstance();
        }
        return $this->_jsonMapper;
    }

    protected function _getXmlMapper()
    {
        if (!isset($this->_xmlMapper)) {
            $this->_xmlMapper = $this->_weaselFactory->getXmlMapperInstance();
        }
        return $this->_xmlMapper;
    }

    public function setWeaselFactory(WeaselFactory $weaselFactory)
    {
        $this->_weaselFactory = $weaselFactory;
    }

    public function setJsonMapper(JsonMapper $jsonMapper)
    {
        $this->_jsonMapper = $jsonMapper;
    }

    public function setXmlMapper(XmlMapper $xmlMapper)
    {
        $this->_xmlMapper = $xmlMapper;
    }

    public function getClient()
    {
        return $this->_client;
    }

    protected function _getRequestParams(Request $request)
    {
        $rObject = new \ReflectionObject($request);
        $requestParams = array();
        if (isset($this->_logger)) {
            $this->_logger->debug("Encoding request", array("request" => $request));
        }
        foreach ($rObject->getMethods() as $method) {
            /**
             * @var \ReflectionMethod $method
             */
            $name = $method->getName();
            if ($method->getNumberOfParameters() === 0 && strpos($name, "get") === 0) {
                $property = lcfirst(substr($name, 3));
                $rawValue = $request->$name();
                $value = null;
                if (is_object($rawValue)) {
                    $value = $this->_getJsonMapper()->writeString($rawValue);
                } elseif (is_array($rawValue)) {
                    $value = json_encode($rawValue);
                } elseif (is_bool($rawValue)) {
                    $value = $rawValue ? "true" : "false";
                } else {
                    $value = $rawValue;
                }
                if ($property != 'httpMethod' && isset($value)) {
                    $requestParams[$property] = $value;
                }
            }
        }
        return $requestParams;
    }

    /**
     * @param MooInterface\Request\Request $request
     * @param string $responseType
     * @return \MooPhp\MooInterface\Response\Response
     */
    public function makeRequest(Request $request, $responseType)
    {
        $rawResponse = $this->getRawResponse($request);
        return $this->_handleResponse($rawResponse, $responseType);
    }

    public function getRawResponse(Request $request)
    {
        return
            $this->_client->makeRequest($this->_getRequestParams($request),
                $request->getHttpMethod() == Request::HTTP_GET ? Client\Client::HTTP_GET :
                    Client\Client::HTTP_POST
            );
    }

    public function getFile(Request $request)
    {
        return $this->_client->getFile($this->_getRequestParams($request));
    }

    public function sendFile(Request $request, $fileParam, $responseType)
    {
        $rawResponse = $this->_client->sendFile($this->_getRequestParams($request), $fileParam);
        return $this->_handleResponse($rawResponse, $responseType);
    }

    public function sendFiles(array $requests, $fileParam, $responseType)
    {
        $params = array();
        foreach ($requests as $key => $request) {
            $params[$key] = $this->_getRequestParams($request);
        }
        $rawResponses = $this->_client->sendFiles($params, $fileParam);

        $responses = array();
        foreach ($rawResponses as $key => $rawResponse) {
            try {
                $responses[$key] = $this->_handleResponse($rawResponse, $responseType);
            } catch (\Exception $e) {
                $responses[$key] = $e;
            }
        }
        return $responses;
    }


    protected function _handleResponse($rawResponse, $type)
    {
        if ($type[0] !== '\\') {
            $type = '\MooPhp\MooInterface\Response\\' . $type;
        }
        /**
         * @var \MooPhp\MooInterface\Response\Response $object
         */
        $object = $this->_getJsonMapper()->readString($rawResponse, $type);

        if (isset($this->_logger)) {
            $this->_logger->debug("Decoded response to ", array("object" => $object));
        }

        if ($object->getException()) {
            throw $object->getException();
        }
        return $object;
    }

    /**
     * @param PhysicalSpec $physicalSpec
     * @param MooInterface\Data\Pack $pack
     * @param null $friendlyName
     * @param null $trackingId
     * @param null $startAgainUrl
     * @param null $designCode
     * @return MooInterface\Response\CreatePack
     */
    public function packCreatePack(PhysicalSpec $physicalSpec,
                                   MooInterface\Data\Pack $pack = null,
                                   $friendlyName = null,
                                   $trackingId = null,
                                   $startAgainUrl = null,
                                   $designCode = null)
    {
        $request = new MooInterface\Request\CreatePack();
        $request->setPack($pack);
        $request->setPhysicalSpec($physicalSpec);
        $request->setTrackingId($trackingId);
        $request->setFriendlyName($friendlyName);
        $request->setStartAgainUrl($startAgainUrl);
        $request->setDesignCode($designCode);
        return $this->makeRequest($request, "CreatePack");
    }

    public function packCreateTrialPartnerPack(PhysicalSpec $physicalSpec,
                                               MooInterface\Data\Pack $pack = null,
                                               $friendlyName = null,
                                               $trackingId = null,
                                               $startAgainUrl = null,
                                               $trialPartner = null,
                                               $designCode = null)
    {
        $request = new MooInterface\Request\CreateTrialPartnerPack();
        $request->setPack($pack);
        $request->setPhysicalSpec($physicalSpec);
        $request->setTrackingId($trackingId);
        $request->setFriendlyName($friendlyName);
        $request->setStartAgainUrl($startAgainUrl);
        $request->setTrialPartner($trialPartner);
        $request->setDesignCode($designCode);
        return $this->makeRequest($request, "CreateTrialPartnerPack");
    }

    /**
     * Get a Moo pack from the builder store.
     * This requires read permissions, which you ought to have.
     * Note that once you've handed off the user to a dropIn URL the pack becomes "owned" and you cannot read it anymore.
     * This may change in a future API version.
     * @param string $packId The pack ID to get
     * @return MooInterface\Response\GetPack
     */
    public function packGetPack($packId)
    {
        $request = new MooInterface\Request\GetPack();
        $request->setPackId($packId);
        return $this->makeRequest($request, "GetPack");
    }

    /**
     * Update a Moo pack on the builder store.
     * This requires update permissions, which you ought to have.
     * Note that once you've handed off the user to a dropIn URL the pack becomes "owned" and you cannot update it anymore.
     * @param string $packId The pack to update
     * @param MooInterface\Data\Pack $pack The new pack data
     * @return MooInterface\Response\UpdatePack
     */
    public function packUpdatePack($packId, MooInterface\Data\Pack $pack)
    {
        $request = new MooInterface\Request\UpdatePack();
        $request->setPackId($packId);
        $request->setPack($pack);
        return $this->makeRequest($request, "UpdatePack");
    }

    /**
     * Add a Moo pack from the builder store to the cart.
     * Note that you don't have the ability to do this by default as it requires the cart permission.
     * If 2 legged OAuth is used this applies to the session of the client making the HTTP request, i.e. this API client
     * @param string $packId The pack ID to add
     * @param int $quantity
     * @return MooInterface\Response\AddToCart
     */
    public function packAddToCart($packId, $quantity = 1)
    {
        $request = new MooInterface\Request\AddToCart();
        $request->setPackId($packId, $quantity);
        return $this->makeRequest($request, "AddToCart");
    }

    /**
     * Get the template XML for a template.
     * Implementations of this API are not expected to deserialize the XML.
     * Requires get_template permission which is granted to everyone.
     * @param string $templateCode The template to retrieve
     * @return \MooPhp\MooInterface\Data\Template\Template
     */
    public function templateGetTemplate($templateCode)
    {
        $request = new MooInterface\Request\GetTemplate();
        $request->setTemplateCode($templateCode);

        $rawResponse = $this->getFile($request);
        $object = $this->_getXmlMapper()->readString($rawResponse,
            '\MooPhp\MooInterface\Data\Template\Template',
            'http://www.moo.com/xsd/template-1.0'
        );
        if (isset($this->_logger)) {
            $this->_logger->debug("Decoded response to ", array("object" => $object));
        }
        return $object;
    }

    /**
     * Upload a local image to the Moo servers.
     * Will take an ImageResource, which could be wrapping a file or some binary and feed it to moo.
     * Requires upload_image permission, which is granted to everyone.
     * @param string $imageFile path to the image to import
     * @param string $imageType Type of image from the IMAGE_TYPE_ constants. Default is unknown which will not trigger image enhance by default.
     * @throws \InvalidArgumentException
     * @return \MooPhp\MooInterface\Response\UploadImage
     */
    public function imageUploadImage($imageFile, $imageType = self::IMAGE_TYPE_UNKNOWN)
    {

        $imageFilePath = realpath($imageFile);

        if (!$imageFilePath || !is_file($imageFilePath) || !is_readable($imageFilePath)) {
            throw new \InvalidArgumentException("Cannot access file $imageFile");
        }

        $request = new MooInterface\Request\UploadImage();
        $request->setImageType($imageType);
        $request->setImageFile($imageFilePath);

        return $this->sendFile($request, "imageFile", "UploadImage");

    }

    /**
     * @param array $images
     * @param string $imageType
     * @return array|MooInterface\Response\UploadImage[]
     * @throws \InvalidArgumentException
     */
    public function imageUploadImages(array $images, $imageType = self::IMAGE_TYPE_UNKNOWN)
    {
        $requests = array();
        foreach ($images as $key => $image) {
            if ($image instanceof Image) {
                $imageFile = $image->getImageFile();
                $thisImageType = $image->getImageType();
            } else {
                $imageFile = $image;
                $thisImageType = $imageType;
            }
            $imageFilePath = realpath($imageFile);
            if (!$imageFilePath || !is_file($imageFilePath) || !is_readable($imageFilePath)) {
                throw new \InvalidArgumentException("Cannot access file $imageFile");
            }

            $request = new MooInterface\Request\UploadImage();
            $request->setImageType($thisImageType);
            $request->setImageFile($imageFilePath);
            $requests[$key] = $request;
        }
        return $this->sendFiles($requests, "imageFile", "UploadImage");
    }

    /**
     * Ask Moo's servers to grab an image from a URL and import it.
     * Requires import_image which is NOT granted by default.
     * @param string $url URL to obtain the image from
     * @param string $imageType Type of image from the IMAGE_TYPE_ constants. Default is unknown which will not trigger image enhance by default.
     * @return MooInterface\Response\ImportImage
     */
    public function imageImportImage($url, $imageType = self::IMAGE_TYPE_UNKNOWN)
    {
        $request = new MooInterface\Request\ImportImage();
        $request->setImageType($imageType);
        $request->setImageUrl($url);
        return $this->makeRequest($request, "ImportImage");
    }

    /**
     * Update the physical spec on a pack.
     * @param string $packId
     * @param \MooPhp\MooInterface\Data\PhysicalSpec $physicalSpec
     * @return MooInterface\Response\UpdatePhysicalSpec
     */
    public function updatePhysicalSpec($packId, PhysicalSpec $physicalSpec)
    {
        $request = new MooInterface\Request\UpdatePhysicalSpec();
        $request->setPackId($packId);
        $request->setPhysicalSpec($physicalSpec);
        return $this->makeRequest($request, "UpdatePhsyicalSpec");
    }

    /**
     * @param string $text The text to measure
     * @param float $fontSize The font size in $units
     * @param \MooPhp\MooInterface\Data\FontSpec $font Font to use for the measurement
     * @param float $wrappingWidth Width in mm after which to wrap to a new line (for multi-line text areas)
     * @param float $leading line spacing as a multiple of the default for the font.
     * @param string $fontSizeUnits Unit of measurement for the font size
     * @throws \InvalidArgumentException
     * @return \MooPhp\MooInterface\Response\TextMeasure
     */
    public function textMeasure($text,
                                $fontSize,
                                FontSpec $font,
                                $wrappingWidth = null,
                                $leading = null,
                                $fontSizeUnits = self::UNIT_MILLIMETERS)
    {
        $request = new MooInterface\Request\TextMeasure();
        $request->setText($text);
        $request->setFontSize($fontSize);
        $request->setFont($font);
        $request->setWrappingWidth($wrappingWidth);
        $request->setLeading($leading);
        $request->setFontSizeUnits($fontSizeUnits);
        return $this->makeRequest($request, "TextMeasure");
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * Render a side to PNG, returning the PNG.
     *
     * @param Side $side The side to render.
     * @param ImageBasket $imageBasket Image basket containing at least all of the images used by the side.
     * @param string $boxType Box to render. One of the BOX_* consts, defaults to print.
     * @param int $maxSide Default 1500px. The longest side of the output image.
     * @param Side[] $overlays An array of sides to draw on top of $side. Used for watermarks.
     * @return string a PNG
     */
    public function packRenderSide(Side $side, ImageBasket $imageBasket, $boxType = self::BOX_PRINT, $maxSide = 1500, array $overlays = null)
    {
        $request = new RenderSide();
        $request->setSide($side);
        $request->setImageBasket($imageBasket);
        $request->setBoxType($boxType);
        $request->setMaxSide($maxSide);
        $request->setOverlays($overlays);
        return $this->getRawResponse($request);
    }

    /**
     * Render a side to PNG and return a URL to fetch it.
     *
     * @param Side $side The side to render.
     * @param ImageBasket $imageBasket Image basket containing at least all of the images used by the side.
     * @param string $boxType Box to render. One of the BOX_* consts, defaults to print.
     * @param int $maxSide Default 1500px. The longest side of the output image.
     * @param Side[] $overlays An array of sides to draw on top of $side. Used for watermarks.
     * @return string a URL to the PNG. This will expire after a few hours.
     */
    public function packRenderSideUrl(Side $side, ImageBasket $imageBasket, $boxType = self::BOX_PRINT, $maxSide = 1500, array $overlays = null)
    {
        $request = new RenderSideUrl();
        $request->setSide($side);
        $request->setImageBasket($imageBasket);
        $request->setBoxType($boxType);
        $request->setMaxSide($maxSide);
        $request->setOverlays($overlays);
        return $this->getRawResponse($request);
    }
}

