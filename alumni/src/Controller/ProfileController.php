<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Form\ProfileFormType;
use Symfony\Component\HttpFoundation\File\File;
use App\Entity\Document;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Form\CvFormType;
use App\Entity\Cv;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class ProfileController extends Controller{

    public function profile()
    {
        $user = $this->getUser();
        $manager = $this->getDoctrine()->getManager();


        $userid = $user->getId();
        $cv = $manager->getRepository(Cv::class)->findOneBy(['user' => $userid]);
        if($cv){
            $document = $cv->getDocument();
        }
        else{
            $document = null;
        }

        return $this->render('Default/profile.html.twig', ['user' => $user, 'cv' => $document]
        );
    }

    public function profileEdit(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $picture = $user->getProfilePicture();
        if($picture)
        {
            $file = new File($picture->getPath() . '/' . $picture->getName());
            $user->setProfilePicture($file);
        }

        $profileForm = $this->createForm(ProfileFormType::class, $user, ['standalone' => true]);
        $profileForm->handleRequest($request);
        
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            
            $file = $user->getProfilePicture();
            if($file){
                $document = new Document();
                $document->setPath($this->getParameter('upload_dir'))
                    ->setMimeType($file->getMimeType())
                    ->setName($file->getFilename());
                $file->move($this->getParameter('upload_dir'));
                
                $user->setProfilePicture($document);
                $manager->persist($document);

                if($picture){
                    $manager->remove($picture);
                }
            }
            else{
                $user->setProfilePicture($picture);
            }

            $manager->flush();
            
            return $this->redirectToRoute('profile');
        }
        /*
        very important! ->previous bug
        */
        $user->setProfilePicture($picture);
        
        return $this->render(
            'Default/profileEdit.html.twig',
            [
                'user'=>$user,
                'profileForm' => $profileForm->createView()
            ]
        );
    }


    public function downloadDocument(Document $document)
    {
        $fileName = sprintf(
            '%s/%s',
            $document->getPath(),
            $document->getName()
            );

        return new BinaryFileResponse($fileName);
    }


    public function cvEdit(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $userid = $user->getId();


        $oldcv = $manager->getRepository(Cv::class)->findOneBy(['user' => $userid]);

        if($oldcv)
        {
            $doc = $oldcv->getDocument();
            $file = new File($oldcv->getDocument()->getPath() . '/' . $oldcv->getDocument()->getName());
            $oldcv->setDocument($file);
        } else {
            $oldcv = new Cv();
            $oldcv->setUser($user);
            $doc = $oldcv->getDocument();
        }

        $cvForm = $this->createForm(CvFormType::class, $oldcv, ['standalone' => true]);
        $cvForm->handleRequest($request);
        
        if ($cvForm->isSubmitted() && $cvForm->isValid()) {

            $file = $oldcv->getDocument();
            $fileName = $this->generateUniqueFileName().'.'.$file->guessExtension();

            if($file){
                $document = new Document();

                $document->setPath($this->getParameter('upload_dir'))
                    ->setMimeType($file->getMimeType())
                    ->setName($file->getFilename());

                $file->move($this->getParameter('upload_dir'));

                $oldcv->setDocument($document);

                $manager->persist($oldcv);
            }
            $manager->flush();
            return $this->redirectToRoute('profile');
        }

        $oldcv->setDocument($doc);

        return $this->render(
            'Default/cv.html.twig',
            [
                'cvForm' => $cvForm->createView()
            ]
        );
    }
    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }

}