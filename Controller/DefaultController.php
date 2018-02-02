<?php

namespace Donfelice\CSVImportExportBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\Repository;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\FieldTypeService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\ContentService;
use \eZ\Publish\API\Repository\UserService;
//use eZ\Publish\Core\Helper\TranslationHelper;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use eZ\Publish\API\Repository\Values\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Visibility;

//use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
//use Symfony\Component\Serializer\Serializer;
//use Symfony\Component\Serializer\Encoder\CsvEncoder;
//use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;


class DefaultController extends Controller
{

    /*
    protected $fileContentArray = array();
    protected $addContentResultArray = array();
    protected $logArray = array();
    */


    private $contentService;
    private $contentTypeService;
    private $fieldTypeService;
    private $searchService;
    private $locationService;
    private $userService;

    /**
     * DefaultController constructor.
     *
     * @param \eZ\Publish\API\Repository\ContentService $contentService
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     * @param \eZ\Publish\API\Repository\FieldTypeService $fieldTypeService
     * @param \eZ\Publish\API\Repository\SearchService $searchService
     * @param \eZ\Publish\API\Repository\LocationService $locationService
     * @param \eZ\Publish\API\Repository\UserService $userService
     */
    public function __construct(
        ContentService $contentService,
        ContentTypeService $contentTypeService,
        FieldTypeService $fieldTypeService,
        SearchService $searchService,
        LocationService $locationService,
        UserService $userService
    )
    {
        $this->contentService = $contentService;
        $this->contentTypeService = $contentTypeService;
        $this->fieldTypeService = $fieldTypeService;
        $this->searchService = $searchService;
        $this->locationService = $locationService;
        $this->userService = $userService;
    }

    /**
     * Method for import from CSV files
     *
     *
     */
    public function importAction( Request $request, $step, $fileName, $contentType, $locationId, $language )
    {
        // Parameters from yml
        $username = $this->getParameter('csvimportexport.username');
        $password = $this->getParameter('csvimportexport.password');

        $name = ""; // file name to be use for reference in step 3

        $fileContentArray = array();
        $addContentResultArray = array();
        $logArray = array();

        //$language = "eng-GB"; // TODO make dynamic with fallback

        $contentTypeGroupId = '1'; //content

        // Set current user
        $repository = $this->getRepository();
        $user = $this->userService->loadUserByCredentials( $username, $password );
        $repository->setCurrentUser( $user );

        // Get available languages
        $availableLanguages = $this->getConfigResolver()->getParameter( 'languages' );

        $contentTypeGroup = $this->contentTypeService->loadContentTypeGroup( $contentTypeGroupId );
        $contentTypes = $this->contentTypeService->loadContentTypes( $contentTypeGroup );

        // Step 1
        if ( $request->isMethod('POST') && $step == "1" ) {

            $file = $this->createFile( $request );

            $filePath = $file[0];
            $fileName = $file[1];

            $fileContentArray = $this->file2Array( $filePath );

        }

        // Step 2
        if ( $fileName != "" && $step == 2 ) {

            // get all existing object of selected content type
            $allExisting = $this->allExisting( $contentType, $language )[0];

            // Get uploaded CSV file
            $filePath = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $fileName;

            $fileContentArray = $this->file2Array( $filePath );
            $fileContentTmpArray = array();

            foreach ( $fileContentArray as $tmp ) {

                //var_dump($allExisting);
                $tmp_string = implode("--", $tmp);

                if ( $allExisting != NULL ) {
                    if ( in_array( $tmp_string, $allExisting ) ) {
                        $tmp[] = "1";
                    } else {
                        $tmp[] = "0";
                    }
                } else {
                    $tmp[] = "0";
                }

                $fileContentTmpArray[] = $tmp;

            }

            $fileContentArray = $fileContentTmpArray;

        }

        // Step 3
        if ( $step == "3" ) {

            // get all existing object of selected content type
            $allExisitingArray = $this->allExisting( $contentType, $language );
            $allExistingObjectStrings = $allExisitingArray[0];
            $allExisitingLocations = $allExisitingArray[1];
            $allExisitingContentIds = $allExisitingArray[2];
            $allExistingObjects = $allExisitingArray[3];

            $numberAlreadyInLocation = 0;
            $numberAddedToLocation = 0;
            $numberNew = 0;

            $targetContentType = $this->contentTypeService->loadContentTypeByIdentifier( $contentType );

            $fieldIdentifiers = array();

            foreach ( $targetContentType->fieldDefinitions as $fieldDefinition ) {
                $fieldIdentifiers[] = $fieldDefinition->identifier;
            }

            $baseurl =  $this->get('kernel')->getRootDir() . "/../web/uploads/csv/";
            $csvurl = $baseurl . $fileName;

            //echo $csvurl;

            /*
            $csvData = $serializer->decode( file_get_contents( $csvurl ), 'csv' );
            $this->utf8_encode_deep( $csvData );
            var_dump($csvData);
            */

            if ( $file = fopen( $csvurl , 'r' ) ) {

                // Loop file line by line
                while ( $data = fgetcsv ( $file, 1000, ";" ) ) {

                    $data = array_map( "utf8_encode", $data ); //added
                    $num = count( $data ); // column count

                    //foreach ( $data as $tmp ) {

                    $tmp_string = trim( implode( "--", $data ) );
                    //echo $tmp_string . "<br><br>";
                    //var_dump($allExistingObjectStrings);
                    //echo "<br><br>";

                    if ( $allExistingObjectStrings != NULL ) {

                        $key = array_search( $tmp_string, $allExistingObjectStrings );

                        if ( $key > -1 ) {

                            // If exists, add new location if not already there
                            if ( in_array( $locationId, $allExisitingLocations ) ){
                                // Already in location, do nothing
                                $numberAlreadyInLocation++;

                            } else {
                                // Object exists, but not in location. Add to this location
                                $addLocationResult = $this->addLocation( $allExistingObjects[ $key ], $locationId, $allExisitingContentIds[ $key ] );
                                $numberAddedToLocation++;
                            }
                        } else {
                            // If new, simply publish new object
                            $addContentResult = $this->addContent(  $data, $targetContentType, $fieldIdentifiers, $locationId  );
                            $addContentResultArray[] = $addContentResult;
                            $numberNew++;
                        }

                    } else {
                        // No objects of this type exists. Publish new object
                        $addContentResult = $this->addContent(  $data, $targetContentType, $fieldIdentifiers, $locationId  );
                        $addContentResultArray[] = $addContentResult;
                        $numberNew++;
                    }

                }

            }

            // Flash bags
            if ( $numberAlreadyInLocation > 0 ) {
                $this->addFlash("warning", $numberAlreadyInLocation . " object(s) are already in this location and where not imported.");
            }

            if ( $numberAddedToLocation > 0 ) {
                $this->addFlash("warning", $numberAddedToLocation . " object(s) added to new location.");
            }

            $this->addFlash("success", "CSV import was a success. Now get yourself a beer!");

        }

        return $this->render('DonfeliceCSVImportExportBundle:Default:import.html.twig',
            array(
                'file_content' => $fileContentArray,
                'step' => $step,
                'content_types' => $contentTypes,
                'content_type' => $contentType,
                'file_name' => $fileName,
                'content_added' => $addContentResultArray,
                'available_languages' => $availableLanguages
            )
        );
    }

    /**
     * Method for export to CSV files
     *
     *
     */
    public function exportAction( Request $request, $step, $locationId, $language )
    {



        return $this->render('DonfeliceCSVImportExportBundle:Default:export.html.twig',
            array(
                'step' => $step
            )
        );

    }


    public function cleanAction( Request $request, $contentType, $locationId, $confirm )
    {

        $contentTypeGroupId = "1"; // Content

        $contentTypeGroup = $this->contentTypeService->loadContentTypeGroup( $contentTypeGroupId );
        $contentTypes = $this->contentTypeService->loadContentTypes( $contentTypeGroup );

        $location = $this->locationService->loadLocation( $locationId );
        $locationChildren = $this->locationService->loadLocationChildren( $location, 0, 1000 );

        // Delete
        if ( $confirm == "yes" ) {
            foreach ( $locationChildren->locations as $item ) {
                $this->contentService->deleteContent( $item->contentInfo );
            }
        }

        return $this->render('DonfeliceCSVImportExportBundle:Default:clean.html.twig',
            array(
                'content_type' => $contentType,
                'location_id' => $locationId,
                'location_children' => $locationChildren
            )
        );

    }


    public function createFile( $request ) {

        $dir = $this->get('kernel')->getRootDir() . '/../web/uploads/csv/';
        $name = uniqid() . '.csv';
        $fileName = $name;

        foreach ( $request->files as $uploadedFile ) {
            $uploadedFile->move( $dir, $name );
        }

        $filePath = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $name;

        return array( $filePath, $fileName );

    }


    public function file2Array( $filePath ) {

        $csv_file = fopen( $filePath,"r" );

        while ( ( $data = fgetcsv( $csv_file, 0, ";" ) ) !== FALSE ) {

            $tmp = array();
            foreach ( $data as $item ) {

                //$item = iconv( "ISO-8859-1" , "UTF-8", $item );
                if( !mb_detect_encoding( $item, 'utf-8', true ) ){
                    $item = utf8_encode( $item );
                }
                $tmp[] = $item;
            }
            $fileContentArray[] = $tmp;

        }

        return $fileContentArray;

    }


    public function allExisting( $contentType, $language ) {

        $allExistingObjects = array();
        $allExistingObjectStrings = array();
        $allExisitingLocations = array();
        $allExisitingContentIds = array();

        // get all existing object of selected content type
        $query = new Query(
            array(
                'filter' => new LogicalAnd(
                    array(
                        //new LocationId( $locationBId ),
                        new ContentTypeIdentifier( $contentType ),
                        new Visibility( Visibility::VISIBLE ),
                    )
                )
            )
        );

        $query->limit = 1000; // TODO config-wise

        $searchResult = $this->searchService->findContent( $query );

        $contentTypeObject = $this->contentTypeService->loadContentTypeByIdentifier( $contentType );

        foreach ( $searchResult->searchHits as $searchHit ){

            $allExistingObjects[] = $searchHit->valueObject;
            $allExisitingContentIds[] = $searchHit->valueObject->contentInfo->id;

            $allLocations = $this->locationService->loadLocations( $searchHit->valueObject->contentInfo );
            foreach ( $allLocations as $item ) {
                $allExisitingLocations[] = $item->parentLocationId;
            }

            $tmp = array();
            $content = $this->contentService->loadContent( $searchHit->valueObject->contentInfo->id, array( $language ));

            foreach( $contentTypeObject->fieldDefinitions as $fieldDefinition ){

                $fieldType = $this->fieldTypeService->getFieldType( $fieldDefinition->fieldTypeIdentifier );
                $field = $content->getFieldValue( $fieldDefinition->identifier, $language );

                if ( $fieldDefinition->fieldTypeIdentifier == 'ezstring' ){
                    $tmp[] = $field->text;
                }
                elseif ( $fieldDefinition->fieldTypeIdentifier == 'ezemail' ){
                    $tmp[] = $field->email;
                }

            }
            $allExistingObjectStrings[] = trim( implode( "--", $tmp ) );
        }

        return array( $allExistingObjectStrings, $allExisitingLocations, $allExisitingContentIds, $allExistingObjects );

    }

    public function addLocation( $contentInfo, $locationId, $contentId ) {

        try
        {
            $locationCreateStruct = $this->locationService->newLocationCreateStruct( $locationId );
            $contentInfo = $this->contentService->loadContentInfo( $contentId );
            $newLocation = $this->locationService->createLocation( $contentInfo, $locationCreateStruct );
        }
        // Content or location not found
        catch ( \eZ\Publish\API\Repository\Exceptions\NotFoundException $e )
        {
            $output->writeln( $e->getMessage() );
        }
        // Permission denied
        catch ( \eZ\Publish\API\Repository\Exceptions\UnauthorizedException $e )
        {
            $output->writeln( $e->getMessage() );
        }
    }


    public function addContent( $data, $targetContentType, $fieldIdentifiers, $locationId ){

        try {

            $contentCreateStruct = $this->contentService->newContentCreateStruct( $targetContentType, 'eng-GB' );

            // Loop row and map columns
            foreach ( $data as $key=>$value ) {
                $contentCreateStruct->setField( $fieldIdentifiers[ $key ], $value );
            }

            // Remote id
            /*
            if (!empty($contentCreateStruct->remoteId)) {
                try {
                    $this->loadContentByRemoteId($contentCreateStruct->remoteId);
                    throw new InvalidArgumentException(
                        '$contentCreateStruct',
                        "Another content with remoteId '{$contentCreateStruct->remoteId}' exists"
                    );
                } catch (APINotFoundException $e) {
                    // Do nothing
                }
            } else {
                //$contentCreateStruct->remoteId = $this->domainMapper->getUniqueHash($contentCreateStruct);
                $remoteId = md5( trim( implode( "-", $data ) ) );
                $contentCreateStruct->remoteId = $remoteId;
                //echo $remoteId . "<br>";
                //echo $contentCreateStruct->remoteId . "<hr>";
            }
            */

            //echo "<hr>";

            $locationCreateStruct = $this->locationService->newLocationCreateStruct( $locationId );

            $draft = $this->contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
            $content = $this->contentService->publishVersion( $draft->versionInfo );

            // Make string for log
            $id = $content->versionInfo->contentInfo->id;
            $name = $content->versionInfo->contentInfo->name;
            $publishedDate = $content->versionInfo->contentInfo->publishedDate;
            $publishedDateString = $publishedDate->format('Y-m-d H:i:s');

            $addContentResult[] = array( $id, $name, $publishedDateString );

        }
        // Content type or location not found
        catch ( \eZ\Publish\API\Repository\Exceptions\NotFoundException $e ) {
            echo $e->getMessage();
        }
        /*
        // Invalid field value
        catch ( \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException $e ) {
            echo $e->getMessage();
            // Required field missing or empty
            catch ( \eZ\Publish\API\Repository\Exceptions\ContentValidationException $e ) {
                echo $e->getMessage();
            }

        }
          }*/

        return $addContentResult;
    }

}
