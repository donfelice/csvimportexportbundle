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


class DefaultController extends Controller
{

    public function indexAction()
    {
        return $this->render('DonfeliceCSVImportExportBundle:Default:index.html.twig');
    }

    public function importAction( Request $request, $step, $fileName, $contentType, $location )
    {
        //$clientid = $this->getParameter('analytics.clientid');

        $name = ""; // file name to be use for reference in step 3
        //$contentType
        $fileContentArray = array();
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
            $allExisting = $this->allExisting( $contentType, $language );

            // Get uploaded CSV file
            $filePath = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $fileName;

            $fileContentArray = $this->file2Array( $filePath );
            $fileContentTmpArray = array();

            foreach ( $fileContentArray as $tmp ) {

                //var_dump($allExisting);
                $tmp_string = implode("--", $tmp);

                if ( $allExisting != NULL ) {
                    //echo $tmp_string;
                    if ( in_array( $tmp_string, $allExisting ) ) {
                        // Allready exists
                        //echo "yes" . "<br>";
                        $tmp[] = "1";
                    } else {
                        // New object
                        //echo "no" . "<br>";
                        $tmp[] = "0";
                    }
                } else {
                    //echo "allExisiting is NULL: " . $tmp_string;
                    $tmp[] = "0";
                }

                $fileContentTmpArray[] = $tmp;

            }

            $fileContentArray = $fileContentTmpArray;

        }

        // Step 3
        if ( $step == "3" ) {

            $userService = $repository->getUserService();
            $user = $userService->loadUserByCredentials( 'admin', 'publish' ); // TODO Get from yml!
            $repository->setCurrentUser( $user );

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

            if ( $file = fopen( $csvurl , 'r' ) ) {

                while ( $data = fgetcsv ( $file, 1000, ";" ) ) {

                  $data = array_map( "utf8_encode", $data ); //added
                  $num = count( $data ); // column count

                  try {

                      $contentCreateStruct = $contentService->newContentCreateStruct( $targetContentType, 'eng-GB' );

                      foreach ( $data as $key=>$value ) {
                          $contentCreateStruct->setField( $fieldIdentifiers[ $key ], $value );
                      }

                      $locationCreateStruct = $locationService->newLocationCreateStruct( $location );

                      $draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
                      $content = $contentService->publishVersion( $draft->versionInfo );

                      //var_dump($content->versionInfo->contentInfo->publishedDate);
                      $id = $content->versionInfo->contentInfo->id;
                      $name = $content->versionInfo->contentInfo->name;
                      $publishedDate = $content->versionInfo->contentInfo->publishedDate;
                      $publishedDateString = $publishedDate->format('Y-m-d H:i:s');

                      $logArray[] = array( $id, $name, $publishedDateString );

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

                }

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
                'log_array' => $logArray
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

            //echo $confirm;
            //var_dump($locationChildren.locations);
            foreach ( $locationChildren->locations as $item ) {

                //var_dump($item->contentInfo);
                $contentService->deleteContent($item->contentInfo);

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

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
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
        $allExisting = array();

        $contentTypeObject = $contentTypeService->loadContentTypeByIdentifier( $contentType );

        foreach ( $searchResult->searchHits as $searchHit ){

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
            $allExisting[] = implode("--", $tmp);
            //$allExisting[] = $tmp;

        }

    }

}
