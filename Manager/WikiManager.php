<?php
/**
 * This file is part of the Claroline Connect package
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * Author: Panagiotis TSAVDARIS
 * 
 * Date: 3/11/15
 */

namespace Icap\WikiBundle\Manager;

use Claroline\CoreBundle\Entity\SecurityToken;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Persistence\ObjectManager;
use JMS\DiExtraBundle\Annotation as DI;
use Icap\WikiBundle\Entity\Wiki;
use Icap\WikiBundle\Entity\Section;
use Icap\WikiBundle\Entity\Contribution;

/**
 * @DI\Service("icap.wiki.manager")
 */
class WikiManager {
    /**
     * @var \Claroline\CoreBundle\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var \Icap\WikiBundle\Repository\SectionRepository
     */
    private $sectionRepository;

    /**
     * @var \Icap\WikiBundle\Repository\ContributionRepository
     */
    private $contributionRepository;

    /**
     * @var \Claroline\CoreBundle\Repository\UserRepository
     */
    private $userRepository;

    /**
     * @DI\InjectParams({
     *      "om"        = @DI\Inject("claroline.persistence.object_manager")
     * })
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
        $this->sectionRepository = $this->om->getRepository('IcapWikiBundle:Section');
        $this->contributionRepository = $this->om->getRepository('IcapWikiBundle:Contribution');
        $this->userRepository = $this->om->getRepository('ClarolineCoreBundle:User');
    }

    public function copyWiki(Wiki $orgWiki, $loggedUser)
    {
        $orgRoot = $orgWiki->getRoot();

        $sections = $this->sectionRepository->children($orgRoot);
        array_unshift($sections, $orgRoot);
        $newSectionsMap = array();

        $newWiki = new Wiki();
        $newWiki->setWikiCreator($loggedUser);

        foreach ($sections as $section) {
            $newSection = new Section();
            $newSection->setWiki($newWiki);
            $newSection->setVisible($section->getVisible());
            $newSection->setAuthor($loggedUser);

            $activeContribution = new Contribution();
            $activeContribution->setTitle($section->getActiveContribution()->getTitle());
            $activeContribution->setText($section->getActiveContribution()->getText());
            $activeContribution->setSection($newSection);
            $activeContribution->setContributor($loggedUser);
            $newSection->setActiveContribution($activeContribution);

            if ($section->isRoot()) {
                $newWiki->setRoot($newSection);
                $this->om->persist($newWiki);
                $this->sectionRepository->persistAsFirstChild($newSection);
            } else {
                $newSectionParent = $newSectionsMap[$section->getParent()->getId()];
                $newSection->setParent($newSectionParent);
                $this->sectionRepository->persistAsLastChildOf($newSection, $newSectionParent);
            }
            $this->om->persist($activeContribution);

            $newSectionsMap[$section->getId()] = $newSection;
        }

        return $newWiki;
    }

    /**
     * Imports wiki object from array
     * (see WikiImporter for structure and description)
     *
     * @param array $data
     * @param $rootPath
     * @param $loggedUser
     *
     * @return Wiki
     */
    public function importWiki(array $data, $rootPath, $loggedUser)
    {
        $wiki = new Wiki();
        if (isset($data['data'])) {
            $wikiData = $data['data'];

            $wiki->setMode($wikiData['options']['mode']);
            $sectionsMap = array();
            foreach ($wikiData['sections'] as $section) {
                $entitySection = new Section();
                $entitySection->setWiki($wiki);
                $entitySection->setDeleted($section['deleted']);
                $entitySection->setDeletionDate($section['deletion_date']);
                $entitySection->setCreationDate($section['creation_date']);
                $author = null;
                if ($section['author'] !== null) {
                    $author = $this->userRepository->findOneByUsername($section['author']);
                }
                if ($author === null) {
                    $author = $loggedUser;
                }
                $entitySection->setAuthor($author);
                $parentSection = null;
                if ($section['parent_id'] !== null) {
                    $parentSection = $sectionsMap[$section['parent_id']];
                    $entitySection->setParent($parentSection);
                }
                if ($section['is_root']) {
                    $wiki->setRoot($entitySection);
                    $this->om->persist($wiki);
                }

                foreach ($section['contributions'] as $contribution) {
                    $contributionData = $contribution['contribution'];
                    $entityContribution = new Contribution();
                    $entityContribution->setSection($entitySection);
                    $entityContribution->setTitle($contributionData['title']);
                    $entityContribution->setCreationDate($contributionData['creation_date']);
                    $contributor = null;
                    if ($contributionData['contributor'] !== null) {
                        $contributor = $this->userRepository->findOneByUsername($contributionData['contributor']);
                    }
                    if ($contributor === null) {
                        $contributor = $loggedUser;
                    }
                    $entityContribution->setContributor($contributor);
                    $text = file_get_contents(
                        $rootPath . DIRECTORY_SEPARATOR . $contributionData['path']
                    );
                    $entityContribution->setText($text);
                    if ($contributionData['is_active']) {
                        $entitySection->setActiveContribution($entityContribution);
                        if ($parentSection !== null) {
                            $this->sectionRepository->persistAsLastChildOf($entitySection, $parentSection);
                        } else {
                            $this->sectionRepository->persistAsFirstChild($entitySection);
                        }
                    }
                    $this->om->persist($entityContribution);
                }
                $sectionsMap[$section['id']] = $entitySection;
            }
        }

        return $wiki;
    }

    /**
     * Exports a Wiki resource
     * according to the description found in WikiImporter
     *
     * @param Workspace $workspace
     * @param array $files
     * @param Wiki $object
     * @return array
     */
    public function exportWiki(Workspace $workspace, array &$files, Wiki $object)
    {
        // Getting all sections and building array
        $rootSection = $object->getRoot();
        $sections = $this->sectionRepository->children($rootSection);
        array_unshift($sections, $rootSection);
        $sectionsArray = array();
        foreach ($sections as $section) {

            //Getting all contributions and building contributions array
            $activeContribution = $section->getActiveContribution();
            $contributions = $this->contributionRepository->findAllButActiveForSection($section);
            $contributionsArray = array();
            array_unshift($contributions, $activeContribution);
            foreach ($contributions as $contribution) {
                $uid = uniqid() . '.txt';
                $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $uid;
                file_put_contents($tmpPath, $contribution->getText());
                $files[$uid] = $tmpPath;

                $contributionArray = array(
                    'is_active'     => $contribution->getId()==$activeContribution->getId(),
                    'title'         => $contribution->getTitle(),
                    'contributor'   => $contribution->getContributor()->getUsername(),
                    'creation_date' => $contribution->getCreationDate(),
                    'path'          => $uid
                );

                $contributionsArray[] = array('contribution' => $contributionArray);
            }
            $sectionArray = array(
                'id'                => $section->getId(),
                'parent_id'         => ($section->getParent() !== null)?$section->getParent()->getId():null,
                'is_root'           => $section->isRoot(),
                'visible'           => $section->getVisible(),
                'creation_date'     => $section->getCreationDate(),
                'author'            => $section->getAuthor()->getUsername(),
                'deleted'           => $section->getDeleted(),
                'deletion_date'     => $section->getDeletionDate(),
                'contributions'     => $contributionsArray
            );

            $sectionsArray[] = $sectionArray;
        }

        $data = array(
            'options'   => array(
                'mode'  => $object->getMode()
            ),
            'sections'  => $sectionsArray
        );

        return $data;
    }
} 