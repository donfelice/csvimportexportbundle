<?php

namespace Donfelice\CSVImportExportBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\ContentTypeService;
//use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use eZ\Publish\API\Repository\Values\Content;
//use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class DefaultController extends Controller
{

    public function indexAction()
    {
        return $this->render('DonfeliceCSVImportExportBundle:Default:index.html.twig');
    }

    public function importAction( Request $request, $step, $fileName, $contentType, $location )
    {
        //$clientid = $this->getParameter('analytics.clientid');


        //echo $step . $id .  $content_type . $location;

        $name = ""; // file name to be use for reference in step 3
        //$contentType
        $file_content_as_array = array();

        $contentTypeGroupId = '1'; //content

        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();


        $contentTypeGroup = $contentTypeService->loadContentTypeGroup( $contentTypeGroupId );
        $contentTypes = $contentTypeService->loadContentTypes( $contentTypeGroup );

        //var_dump( $contentTypeGroup );
        //var_dump( $contentTypes );


        // Step 1
        if ( $request->isMethod('POST') ) {

            //var_dump($request);

            $dir = $this->get('kernel')->getRootDir() . '/../web/uploads/csv/';
            $name = uniqid() . '.csv';
            $fileName = $name;

            foreach ( $request->files as $uploadedFile ) {
                $uploadedFile->move($dir, $name);
            }

            $file = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $name;

            //var_dump($file);

            //$file_content_as_array = array_map('str_getcsv', file($file));
            $csv_file = fopen($file,"r");
            while (($data = fgetcsv($csv_file, 0, ";")) !== FALSE) {
                //var_dump($data);
                //echo "<br>";
                $tmp = array();
                foreach ( $data as $item ) {

                    //$item = iconv( "ISO-8859-1" , "UTF-8", $item );
                    if( !mb_detect_encoding( $item, 'utf-8', true ) ){
                        $item = utf8_encode( $item );
                    }
                    $tmp[] = $item;
                }
                $file_content_as_array[] = $tmp;
            }

            //$file_content_as_array = fgetcsv($csv_file, 0, ";");
            //var_dump($file_content_as_array);



        }


        // Step 2
        if ( $fileName != "" ) {

            $file = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $fileName;

            //var_dump($file);

            //$file_content_as_array = array_map('str_getcsv', file($file));
            $csv_file = fopen( $file, "r" );
            while ( ( $data = fgetcsv( $csv_file, 0, ";" ) ) !== FALSE) {
                //var_dump($data);
                //echo "<br>";
                $tmp = array();
                foreach ( $data as $item ) {

                    //$item = iconv( "ISO-8859-1" , "UTF-8", $item );
                    if( !mb_detect_encoding( $item, 'utf-8', true ) ){
                        $item = utf8_encode( $item );
                    }
                    $tmp[] = $item;
                }
                $file_content_as_array[] = $tmp;
            }

        }


        // Step 3
        if ( $step == "3" ) {

            $userService = $repository->getUserService();
            $user = $userService->loadUserByCredentials( 'admin', 'publish' ); // TODO Get from yml!
            $repository->setCurrentUser( $user );

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

                  //echo $num;

                  //$geo_id = $data[0];
                  //$geo_name = $data[1];
                  //$geo_population = $data[4];
                  //$geo_area = $data[5];
                  //$geo_logo = $data[6];

                  //echo $geo_id . " > " . $geo_name. " > " . $geo_population . " > " . $geo_area;



                  try {

                      $contentCreateStruct = $contentService->newContentCreateStruct( $targetContentType, 'eng-GB' );

                      foreach ( $data as $key=>$value ) {
                          $contentCreateStruct->setField( $fieldIdentifiers[ $key ], $value );
                      }

                      $locationCreateStruct = $locationService->newLocationCreateStruct( $location );

                      //var_dump($contentCreateStruct);
                      //var_dump($locationCreateStruct);

                      $draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
                      $content = $contentService->publishVersion( $draft->versionInfo );
                      //print_r( $content );


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

        }

        return $this->render('DonfeliceCSVImportExportBundle:Default:import.html.twig',
            array(
                'file_content' => $file_content_as_array,
                'step' => $step,
                'content_types' => $contentTypes,
                'content_type' => $contentType,
                'file_name' => $fileName
            )
        );
    }

}
