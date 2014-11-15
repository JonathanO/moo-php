<?php
use \MooPhp\MooInterface\Data as Data;

/**
 * Demo of fiddling about with a pack on the commandline.
 * This will create a businesscard with an image side, and a details side.
 * There'll be an image on both sides, and some text on the details side.
 *
 * @package packManipulator
 * @author Jonathan Oddy <jonathan@moo.com>
 * @copyright Copyright (c) 2012, Moo Print Ltd.
 */

if (!ini_get("date.timezone")) {
    date_default_timezone_set("UTC");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// You installed this with composer, right?
$loader = require __DIR__ . '/../vendor/autoload.php';
$opts = getopt("k:s:w:t:");

if (!isset($opts["k"]) || !isset($opts['s'])) {
    die("Need to provide key and secret.");
}
if (!isset($opts["t"])) {
    $clientType = "guzzle";
} else {
    $clientType = $opts['t'];
}

$localWeasel = getenv("USE_LOCAL_WEASEL");
if ($localWeasel) {
    $loader->add('Weasel', $localWeasel, true);
}

$key = $opts['k'];
$secret = $opts['s'];

/**
 * Require the client we're going to be using.
 */
require_once(__DIR__ . "/inc/" . $clientType . ".php");
$client = getClient($key, $secret);

$api = new \MooPhp\Api($client);
$weaselFactory = new \Weasel\WeaselDoctrineAnnotationDrivenFactory();

if (class_exists('\Monolog\Logger')) {
    $weaselLogger = new \Monolog\Logger("weasel");
    $clientLogger = new \Monolog\Logger("client");
    $apiLogger = new \Monolog\Logger("api");
    $handler = new \Monolog\Handler\StreamHandler("php://stderr");
    $weaselLogger->pushHandler($handler);
    $clientLogger->pushHandler($handler);
    $apiLogger->pushHandler($handler);

    $weaselFactory->setLogger($weaselLogger);
    $client->setLogger($clientLogger);
    $api->setLogger($apiLogger);
}

$api->setWeaselFactory($weaselFactory);


// Helper that allows us to calculate text sizes
$textHelper = new \MooPhp\Helper\TextHelper($api);

// First we'll create a pack, using the default physical spec (a businesscard product.)
$packResp = $api->packCreatePack(new \MooPhp\MooInterface\Data\PhysicalSpec());
$packId = $packResp->getPackId();
$pack = $packResp->getPack();


// First a details side.

// Lets upload a photo for using on the details side.
$uploadImageResp = $api->imageUploadImage(__DIR__ . '/imgs/poor_kettle_purchasing_decision.jpg');
$basketItem = $uploadImageResp->getImageBasketItem();

// And add it to our pack's imagebasket so we can use it on our cards.
$pack->getImageBasket()->addItem($basketItem);

$detailsTemplateCode = "businesscard_right_image_landscape";

$detailsSide = new Data\Side();
$detailsSide->setType("details")->setTemplateCode($detailsTemplateCode);

// OK, lets put the same image we've already used on the details side.
$imageData = new Data\UserData\ImageData();
$imageData->setLinkId("variable_image_back");

// You may wonder where these numbers came from... well I had to use the Moo flash canvas to make it look right,
// and then pull the data out of the pack data that wrote.
$imageData->setImageBox(new Data\Types\BoundingBox(new Data\Types\Point(78.09, 29.85), 81.81, 61.36));
$imageData->setResourceUri($basketItem->getResourceUri());
$detailsSide->addDatum($imageData);

// We're going to need the template loaded to make use of the TextHelper
$detailsTemplate = $api->templateGetTemplate($detailsTemplateCode);

// We'll add some text too:
$textLine = new Data\UserData\TextData("back_line_1");
$textLine->setFont(new Data\Types\Font("radio"))->setText("Kettles should not burn this well;");
$textLine->setColour(new Data\Types\ColourRGB(255, 0, 0));

// Rather than specifying our own font size, we'll let the text helper fit the text for us.
$textHelper->fitTextData($textLine, $detailsTemplate);

$detailsSide->addDatum($textLine);

// More text, this time with a hardcoded font size
$textLine = new Data\UserData\TextData("back_line_2");
$textLine->setFont(new Data\Types\Font("meta"));
$textLine->setText("Especially if they turn themselves on;");
$textLine->setPointSize(2.65);
$textLine->setColour(new Data\Types\ColourRGB(0, 255, 0));
$detailsSide->addDatum($textLine);

$textLine = new Data\UserData\TextData();
$textLine->setFont(new Data\Types\Font("vagrounded"));
$textLine->setText("This was a poor purchasing decision;");
$textLine->setPointSize(2.65);
$textLine->setColour(new Data\Types\ColourRGB(0, 0, 255));
$textLine->setLinkId("back_line_3");
$detailsSide->addDatum($textLine);

$textLine = new Data\UserData\TextData();
$textLine->setFont(new Data\Types\Font("bryant"));
$textLine->setText("And now I cannot make tea.");
$textLine->setPointSize(3.35);
$textLine->setLinkId("back_line_4");
$detailsSide->addDatum($textLine);

$pack->addSide($detailsSide);

// Lets make some image sides

// Upload  the photos, lets use the multi upload feature this time!

$uploadImageResponses = $api->imageUploadImages(
    array(
        __DIR__ . "/imgs/suboptimal.jpg",
        __DIR__ . "/imgs/oopse.jpg",
    )
);

$imageTemplateCode = "businesscard_full_image_landscape";
$imageTemplate = $api->templateGetTemplate($imageTemplateCode);
$imageTemplateItem = $imageTemplate->getItemByLinkId("variable_image_front");
/**
 * @var \MooPhp\MooInterface\Data\Template\Items\ImageItem $imageTemplateItem
 */
foreach ($uploadImageResponses as $uploadImageResp) {
    $basketItem = $uploadImageResp->getImageBasketItem();
    $pack->getImageBasket()->addItem($basketItem);
    $imageSide = new Data\Side();
    $imageSide->setType("image")->setTemplateCode($imageTemplateCode);

    // We need to work out how to position our image. The TemplateItem for the image allows us to calculate a sane default.
    $printImage = $basketItem->getImageItem("print");
    $imageBox = $imageTemplateItem->calculateDefaultImageBox($printImage->getWidth(), $printImage->getHeight());

    $imageData = new Data\UserData\ImageData();
    $imageData->setLinkId("variable_image_front");
    $imageData->setImageBox($imageBox);
    $imageData->setResourceUri($basketItem->getResourceUri());

    $pack->addSide($imageSide->addDatum($imageData));
}

// If you don't have permission for this, you can just do another createPack with your now populated pack object.
$updateResp = $api->packUpdatePack($packId, $pack);

// OK, all done.

// We might have some warnings (though at time of writing this pack was fine.)
var_dump($updateResp->getWarnings());

// Let's get a rendering of the details side to see what it'll look like.
$renderedUrl = $api->packRenderSideUrl($detailsSide, $pack->getImageBasket());
var_dump($renderedUrl);

// We should also be able to enter the Moo build flow at various points identified by dropins.
var_dump($updateResp->getDropIns());

print "\nAll done!\n";

