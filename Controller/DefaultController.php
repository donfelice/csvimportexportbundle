<?php

namespace Donfelice\CSVImportExportBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\FieldTypeService;
use eZ\Publish\API\Repository\SearchService;
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

    public function indexAction()
    {
        return $this->render('DonfeliceCSVImportExportBundle:Default:index.html.twig');
    }

    public function importAction( Request $request, $step, $fileName, $contentType, $locationId )
    {
        //$clientid = $this->getParameter('analytics.clientid');

        $name = ""; // file name to be use for reference in step 3
        //$contentType
        $fileContentArray = array();
        $addContentResultArray = array();
        $logArray = array();

        $language = "eng-GB"; // TODO make dynamic with fallback

        $contentTypeGroupId = '1'; //content

        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        $searchService = $repository->getSearchService();
        $fieldTypeService = $repository->getFieldTypeService();

        $contentTypeGroup = $contentTypeService->loadContentTypeGroup( $contentTypeGroupId );
        $contentTypes = $contentTypeService->loadContentTypes( $contentTypeGroup );

        //var_dump( $contentTypeGroup );
        //var_dump( $contentTypes );


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
            //echo $contentType . $language;
            $allExisitingArray = $this->allExisting( $contentType, $language );
            $allExistingObjectStrings = $allExisitingArray[0];
            $allExisitingLocations = $allExisitingArray[1];
            $allExisitingContentIds = $allExisitingArray[2];
            $allExistingObjects = $allExisitingArray[3];

            //var_dump($allExisitingContentIds);

            $numberAlreadyInLocation = 0;
            $numberAddedToLocation = 0;

            $userService = $repository->getUserService();
            $user = $userService->loadUserByCredentials( 'admin', 'publish' ); // TODO Get from yml!
            $repository->setCurrentUser( $user );

            //$serializer = new Serializer( [new ObjectNormalizer()], [ new CsvEncoder() ] );

            // instantiation, when using it inside the Symfony framework
            //$serializer = $container->get('serializer');

            // encoding contents in CSV format
            //$serializer->encode($data, 'csv');


            //$this->notificationHandler = new NotificationHandlerInterface;

            $targetContentType = $contentTypeService->loadContentTypeByIdentifier( $contentType );

            //var_dump($targetContentType->fieldDefinitions);

            $fieldIdentifiers = array();


            foreach ( $targetContentType->fieldDefinitions as $fieldDefinition ) {
                //var_dump($fieldDefinition);
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
                                $numberAddedToLocation++;
                                $addLocationResult = $this->addLocation( $allExistingObjects[ $key ], $locationId, $allExisitingContentIds[ $key ] );
                            }
                        } else {
                            // If new, simply publish new object
                            $addContentResult = $this->addContent(  $data, $targetContentType, $fieldIdentifiers, $locationId  );
                            $addContentResultArray[] = $addContentResult;
                        }

                    } else {
                        // No objects of this type exists. Publish new object
                        $addContentResult = $this->addContent(  $data, $targetContentType, $fieldIdentifiers, $locationId  );
                        $addContentResultArray[] = $addContentResult;
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
                'content_added' => $addContentResultArray
            )
        );
    }


    public function cleanAction( Request $request, $contentType, $locationId, $confirm )
    {

        $contentTypeGroupId = "1"; // Content

        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        //$searchService = $repository->getSearchService();
        //$fieldTypeService = $repository->getFieldTypeService();

        $contentTypeGroup = $contentTypeService->loadContentTypeGroup( $contentTypeGroupId );
        $contentTypes = $contentTypeService->loadContentTypes( $contentTypeGroup );

        $location = $locationService->loadLocation( $locationId );
        $locationChildren = $locationService->loadLocationChildren( $location, 0, 1000 );

        // Delete
        if ( $confirm == "yes" ) {
            foreach ( $locationChildren->locations as $item ) {

                //var_dump($item->contentInfo);
                $contentService->deleteContent( $item->contentInfo );

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

    /*
    public function utf8_encode_deep(&$input) {
        if (is_string($input)) {
            $input = utf8_encode($input);
        } else if (is_array($input)) {
            foreach ($input as &$value) {
                $this->utf8_encode_deep($value);
            }

            unset($value);
        } else if (is_object($input)) {
            $vars = array_keys(get_object_vars($input));

            foreach ($vars as $var) {
                $this->utf8_encode_deep($input->$var);
            }
        }
    }
    */


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

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        $locationService = $repository->getLocationService();
        $fieldTypeService = $repository->getFieldTypeService();

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

        $query->limit = 1000;

        $searchResult = $searchService->findContent( $query );
        //var_dump($searchResult);

        $contentTypeObject = $contentTypeService->loadContentTypeByIdentifier( $contentType );

        foreach ( $searchResult->searchHits as $searchHit ){

            $allExistingObjects[] = $searchHit->valueObject;
            $allExisitingContentIds[] = $searchHit->valueObject->contentInfo->id;

            $allLocations = $locationService->loadLocations( $searchHit->valueObject->contentInfo );
            foreach ( $allLocations as $item ) {
                $allExisitingLocations[] = $item->parentLocationId;

            }
            //var_dump($allLocations);

            $tmp = array();
            $content = $contentService->loadContent( $searchHit->valueObject->contentInfo->id, array( $language ));

            foreach( $contentTypeObject->fieldDefinitions as $fieldDefinition ){

                $fieldType = $fieldTypeService->getFieldType( $fieldDefinition->fieldTypeIdentifier );
                $field = $content->getFieldValue( $fieldDefinition->identifier, $language );
                //echo $fieldDefinition->fieldTypeIdentifier;
                //var_dump($fieldType);
                //var_dump($field);

                if ( $fieldDefinition->fieldTypeIdentifier == 'ezstring' ){
                    $tmp[] = $field->text;
                }
                elseif ( $fieldDefinition->fieldTypeIdentifier == 'ezemail' ){
                    $tmp[] = $field->email;
                    //var_dump($field);
                }

            }
            $allExistingObjectStrings[] = trim( implode( "--", $tmp ) );
            //$allExistingObjects[] = $tmp;

        }

        return array( $allExistingObjectStrings, $allExisitingLocations, $allExisitingContentIds, $allExistingObjects );

    }

    public function addLocation( $contentInfo, $locationId, $contentId ) {

        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();

        try
        {
            $locationCreateStruct = $locationService->newLocationCreateStruct( $locationId );
            $contentInfo = $contentService->loadContentInfo( $contentId );
            $newLocation = $locationService->createLocation( $contentInfo, $locationCreateStruct );
            //print_r( $newLocation );
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

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        $locationService = $repository->getLocationService();
        $fieldTypeService = $repository->getFieldTypeService();

        $userService = $repository->getUserService();
        $user = $userService->loadUserByCredentials( 'admin', 'publish' ); // TODO Get from yml!
        $repository->setCurrentUser( $user );


        try {

            $contentCreateStruct = $contentService->newContentCreateStruct( $targetContentType, 'eng-GB' );

            // Loop row and map columns
            foreach ( $data as $key=>$value ) {
                $contentCreateStruct->setField( $fieldIdentifiers[ $key ], $value );
            }

            $locationCreateStruct = $locationService->newLocationCreateStruct( $locationId );

            $draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
            $content = $contentService->publishVersion( $draft->versionInfo );

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
