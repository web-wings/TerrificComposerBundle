<?php
/*
 * This file is part of the Terrific Composer package.
 *
 * (c) Remo Brunschwiler <remo@terrifically.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terrific\ComposerBundle\Service;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Terrific\ComposerBundle\Entity\Page;
use Terrific\ComposerBundle\Entity\Template as ModuleTemplate;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Doctrine\Common\Annotations\AnnotationReader;

/**
 * PageManager.
 */
class PageManager
{
    private $kernel;
    private $container;

    /**
     * Constructor.
     *
     * @param KernelInterface       $kernel       The kernel is used to parse bundle notation
     * @param ContainerInterface    $container    The container is used to load the managers lazily, thus avoiding a circular dependency
     */
    public function __construct(KernelInterface $kernel, ContainerInterface $container)
    {
        $this->kernel = $kernel;
        $this->container = $container;
    }

    /**
     * Gets all existing Pages
     *
     * @return array All existing Page instances
     */
    public function getPages()
    {
        $pages = array();
        $reader = new AnnotationReader();

        $dir = $this->kernel->getRootDir().'/../src/Terrific/CompositionBundle/Controller/';

        $finder = new Finder();
        $finder->files()->in($dir)->depth('== 0')->name('*Controller.php');

        foreach ($finder as $file) {
            $className = str_replace('.php', '', $file->getFilename());
            $c = new \ReflectionClass('\Terrific\CompositionBundle\Controller\\'.$className);

            $methods = $c->getMethods();

            foreach($methods as $method) {
                // check whether the method is an action and therefore a page
                if (strpos($method->getName(), 'Action') !== false) {
                    // setup a fresh page object
                    $page = new Page();
                    $page->setController(substr($className, 0, -10));
                    $action = substr($method->getShortName(), 0, -6);
                    $page->setAction($action);

                    // create name from composer annotation
                    $composerAnnotation = $reader->getMethodAnnotation($method, 'Terrific\ComposerBundle\Annotation\Composer');
                    if($composerAnnotation == null) {
                        $name = $action;
                    }
                    else {
                        $name = $composerAnnotation->getName();
                    }

                    $page->setName($name);

                    // create url from route annotation
                    $routeAnnotation = $reader->getMethodAnnotation($method, 'Symfony\Component\Routing\Annotation\Route');

                    $page->setUrl($this->container->get('router')->generate($routeAnnotation->getName()));

                    $pages[] = $page;
                }
            }
        }

        return $pages;
    }
}