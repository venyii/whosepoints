<?php

namespace Venyii\WhosePoints;

use Assetic\Asset\GlobAsset;
use Assetic\AssetWriter;
use Assetic\Filter\Yui\CssCompressorFilter;
use Assetic\Filter\Yui\JsCompressorFilter;
use Symfony\Component\Finder\Finder;
use Venyii\WhosePoints\Parser\CsvKissesParser;
use Venyii\WhosePoints\Parser\CsvPointsParser;
use Symfony\Component\Filesystem\Filesystem;

class LocalStaticGenerator
{
    private $srcDir;
    private $destDir;
    private $fs;
    private $tpls;

    public function __construct($destDir)
    {
        $this->srcDir = __DIR__.'/../res/tpl/';
        $this->destDir = rtrim($destDir, '/').'/';
        $this->fs = new Filesystem();
    }

    public function generate()
    {
        $twig = $this->createTwig();

        $points = new CsvPointsParser(new \SplFileInfo(__DIR__.'/../res/data/points.csv'));
        $seasons = $points->parse();

        $kisses = new CsvKissesParser(new \SplFileInfo(__DIR__.'/../res/data/kisses.csv'), $seasons, $points->getParticipants());
        $kisses = $kisses->parse();

        $stats = new StatsGenerator($seasons);
        $renderer = new LocalHtmlRenderer($twig);
        $this->tpls = $renderer->render($stats->generateStats(), $seasons, $kisses);

        return $this;
    }

    public function write()
    {
        $this->cleanupOutDir();
        $this->writeOut();

        foreach ($this->tpls as $typeName => $type) {
            if (is_array($type)) {
                foreach ($type as $id => $typ) {
                    $this->fs->dumpFile($this->destDir.$typeName.'/'.$id.'.html', $typ);
                }
            } else {
                $this->fs->dumpFile($this->destDir.$typeName.'.html', $type);
            }
        }

        $this->minifyStatics();
    }

    private function createTwig()
    {
        $loader = new \Twig_Loader_Filesystem(__DIR__.'/../res/tpl');
        $twig = new \Twig_Environment($loader, [
            'debug' => true,
            'strict_variables' => true
        ]);

        return $twig;
    }

    private function cleanupOutDir()
    {
        $finder = new Finder();
        $finder
            ->ignoreDotFiles(false)
            ->notName('.gitkeep')
            ->depth('<1')
            ->in($this->destDir);

        foreach ($finder as $file) {
            $this->fs->remove($file);
        }
    }

    private function writeOut()
    {
        $finder = new Finder();
        $finder
            ->ignoreDotFiles(false)
            ->notName('*.twig')
            ->notName('.gitkeep')
            ->files()
            ->in($this->srcDir);


        foreach ($finder as $file) {
            $this->fs->copy($file->getRealPath(), $this->destDir.$file->getRelativePathname());
        }
    }

    private function minifyStatics()
    {
        $yuiPath = __DIR__.'/../res/compressor/yuicompressor-2.4.8.jar';

        $jsFilter = new JsCompressorFilter($yuiPath);
        $cssFilter = new CssCompressorFilter($yuiPath);

        $jsAssets = new GlobAsset([
            $this->srcDir.'static/js/vendor/jquery-2.1.0.js',
            $this->srcDir.'static/js/vendor/*.js',
            $this->srcDir.'static/js/*.js'
        ], [$jsFilter]);

        $cssAssets = new GlobAsset([
                $this->srcDir.'static/css/bootstrap.css',
                $this->srcDir.'static/css/*.css'
        ], [$cssFilter]);

        $jsAssets->setTargetPath('scripts.js');
        $cssAssets->setTargetPath('styles.css');

        $writer = new AssetWriter(__DIR__.'/../out/static/min');
        $writer->writeAsset($cssAssets);
        $writer->writeAsset($jsAssets);
    }
}
