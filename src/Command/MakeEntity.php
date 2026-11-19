<?php

/*
 * This file is part of the Austral Maker Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\MakerBundle\Command;

use Austral\ToolsBundle\Command\Base\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\String\u;

/**
 * Austral Make Entity Command: generates an Entity, its EntityManager and its Repository.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class MakeEntity extends Command
{
  /**
   * @var string
   */
  protected string $titleCommande = 'Generate Entity / EntityManager / Repository';

  /**
   * {@inheritdoc}
   */
  protected function configure(): void
  {
    $this
      ->setDefinition([
        new InputOption('--all-properties', '-a', InputOption::VALUE_NONE, 'Enabled all properties'),
        new InputOption('--dirname', '-d', InputOption::VALUE_OPTIONAL, 'Defined dir'),
        new InputOption('--path-file', '-p', InputOption::VALUE_OPTIONAL, 'Path file'),
        new InputOption('--namespace', '', InputOption::VALUE_OPTIONAL, 'Namespace'),
      ])
      ->addArgument('name', InputArgument::OPTIONAL, 'Entity name')
      ->setDescription($this->titleCommande)
      ->setHelp(
        <<<'EOF'
          The <info>%command.name%</info> generate Entity / EntityManager / Repository file

            <info>php %command.full_name%</info>
          EOF
      )
    ;
  }

  /**
   * @var array<string, string>
   */
  protected array $paths = [];

  /**
   * @var array<mixed>
   */
  protected array $bundlesList = [];

  /**
   * @var string|null
   */
  protected ?string $namespace = null;

  /**
   * @var string|null
   */
  protected ?string $skeletonPath = null;

  /**
   * @var InputInterface|null
   */
  protected ?InputInterface $input = null;

  /**
   * @var OutputInterface|null
   */
  protected ?OutputInterface $output = null;

  /**
   * @var array<string, string|null>
   */
  protected array $templateParameters = [
    '##php##' => '<?php',
    '##NAME##' => null,
    '##TABLE_NAME##' => null,

    '##ENTITY_NAMESPACE##' => null,
    '##ENTITY_USE##' => null,
    '##ENTITY_ATTRIBUTES##' => null,
    '##ENTITY_INTERFACE##' => null,
    '##ENTITY_TRAITS##' => null,
    '##ENTITY_CONSTRUCT##' => null,
    '##ENTITY_FIELDS##' => null,
    '##ENTITY_DEFAULT_FIELD_TO_STRING##' => null,
    '##ENTITY_GETTER_SETTER##' => null,

    '##ENTITY_MANAGER_NAMESPACE##' => null,

    '##REPOSITORY_NAMESPACE##' => null,
  ];

  /**
   * @var array<string, bool|array<string, bool>>
   */
  protected array $propertiesUsed = [
    'timestampable' => false,
    'blockComponent' => false,
    'translate' => false,
    'domainFilter' => false,
    'url' => false,
    'treePage' => false,
    'file' => false,
    'cropper' => false,
  ];

  /**
   * @param InputInterface  $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  protected function executeCommand(InputInterface $input, OutputInterface $output): void
  {
    $this->input = $input;
    $this->output = $output;

    // The templates are shipped with this bundle (src/Skeleton)
    $skeletonPath = \dirname(__DIR__).'/Skeleton';
    if (!is_dir($skeletonPath))
    {
      throw new \RuntimeException(sprintf('The skeleton directory "%s" does not exist.', $skeletonPath));
    }
    $this->skeletonPath = $skeletonPath;

    $dirname = $this->input->getOption('dirname');
    if (!$pathFile = $this->input->getOption('path-file'))
    {
      $kernelProjectDir = $this->container?->getParameter('kernel.project_dir') ?? '';
      $kernelProjectDir = !is_string($kernelProjectDir) ? '' : $kernelProjectDir;
      $pathFile = "{$kernelProjectDir}/src";
    }

    if (!$namespace = $this->input->getOption('namespace'))
    {
      $namespace = 'App';
    }

    $this->paths = [
      'Entity' => "{$pathFile}/Entity".($dirname ? "/{$dirname}" : ''),
      'EntityManager' => "{$pathFile}/EntityManager".($dirname ? "/{$dirname}" : ''),
      'Repository' => "{$pathFile}/Repository".($dirname ? "/{$dirname}" : ''),
    ];
    $this->templateParameters['##ENTITY_NAMESPACE##'] = "{$namespace}\\Entity".($dirname ? "\\{$dirname}" : '');
    $this->templateParameters['##ENTITY_MANAGER_NAMESPACE##'] = "{$namespace}\\EntityManager".($dirname ? "\\{$dirname}" : '');
    $this->templateParameters['##REPOSITORY_NAMESPACE##'] = "{$namespace}\\Repository".($dirname ? "\\{$dirname}" : '');

    $bundlesList = $this->container?->getParameter('kernel.bundles') ?? '';
    $bundlesList = !is_array($bundlesList) ? [] : $bundlesList;
    $this->bundlesList = $bundlesList;

    $this->create();
  }

  /**
   * bundleIsEnabled
   *
   * @param string $bundleName
   *
   * @return bool
   */
  protected function bundleIsEnabled(string $bundleName): bool
  {
    return array_key_exists($bundleName, $this->bundlesList);
  }

  /**
   * @throws \Exception
   */
  protected function create(): void
  {
    if (!$name = $this->input?->getArgument('name'))
    {
      $name = $this->askName();
    }

    if ($this->input?->getOption('all-properties'))
    {
      /**
       * @var string $key
       * @var mixed  $value
       */
      foreach ($this->propertiesUsed as $key => $value)
      {
        if ('domainFilter' === $key)
        {
          $this->propertiesUsed[$key] = [
            'forAllDomainEnabled' => true,
            'autoDomainId' => true,
          ];
        }
        else
        {
          $this->propertiesUsed[$key] = true;
        }
      }
    }
    else
    {
      $this->askConfirmationQuestion(
        'Entity has created and updated date ? (y|o)',
        'timestampable'
      );

      if ($this->bundleIsEnabled('AustralEntityTranslateBundle'))
      {
        $this->askUsedAustralEntityTranslateBundle();
      }

      if ($this->bundleIsEnabled('AustralContentBlockBundle'))
      {
        $this->askUsedAustralContentBlockBundle();
      }

      if ($this->bundleIsEnabled('AustralHttpBundle'))
      {
        $this->askUsedAustralHttpBundle();
      }

      if ($this->bundleIsEnabled('AustralEntityFileBundle'))
      {
        $this->askUsedAustralEntityFileBundle();
      }

      if ($this->bundleIsEnabled('AustralSeoBundle'))
      {
        $this->askUsedAustralSeoBundle();
      }
    }

    $filesystem = new Filesystem();
    foreach ($this->paths as $path)
    {
      if (!$filesystem->exists($path))
      {
        $filesystem->mkdir($path);
      }
    }

    $this->generateFile($name);
    if (true === $this->propertiesUsed['translate'])
    {
      $this->generateFile($name, true);
    }
  }

  /**
   * askQuestion
   *
   * @param string      $entitled
   * @param string|null $property
   *
   * @return bool
   */
  protected function askConfirmationQuestion(string $entitled, ?string $property = null): bool
  {
    $helper = $this->getHelper('question');
    if ($helper instanceof QuestionHelper && $this->input && $this->output)
    {
      if ($helper->ask($this->input, $this->output, new ConfirmationQuestion($entitled, false, '/^(y|o|1)/i')))
      {
        if (null !== $property)
        {
          $this->propertiesUsed[$property] = true;
        }

        return true;
      }
    }
    if (null !== $property)
    {
      $this->propertiesUsed[$property] = false;
    }

    return false;
  }

  /**
   * generateFile
   *
   * @param string $name        entity name as typed (normalized to a class name: "blog_post" -> "BlogPost")
   * @param bool   $isTranslate generates the translation entity ("BlogPostTranslate") of the entity
   *
   * @return $this
   */
  protected function generateFile(string $name, bool $isTranslate = false): MakeEntity
  {
    // Same class name everywhere: file, class and references between the master and its translation
    $masterName = u($name)->camel()->title()->toString();
    $translateName = "{$masterName}Translate";
    $className = $isTranslate ? $translateName : $masterName;

    $templateParameters = $this->templateParameters;

    $templateParameters['##NAME##'] = $className;
    $templateParameters['##TABLE_NAME##'] = 'app_'.u($className)->snake();
    $templateParameters['##DATE##'] = new \DateTime()->format('Y-m-d H:i:s');

    $useClass = [];
    $traitsClass = [];
    $attributesClass = [];
    $interfacesClass = [];
    $getterSetter = [];
    $fields = [];
    $construct = [];

    if ($this->propertiesUsed['timestampable'])
    {
      $useClass[] = "use Austral\EntityBundle\Entity\Traits\EntityTimestampableTrait;";
      $traitsClass[] = 'use EntityTimestampableTrait;';
    }

    $templateParameters['##ENTITY_DEFAULT_FIELD_TO_STRING##'] = 'return $this->id;';

    if ($this->propertiesUsed['translate'] && false === $isTranslate)
    {
      $useClass[] = "use Austral\Contracts\Translate\TranslateMasterInterface;";
      if ($this->propertiesUsed['cropper'])
      {
        // Includes EntityTranslateMasterTrait: both cannot be used together (method collisions)
        $useClass[] = "use Austral\EntityFileBundle\Entity\Traits\EntityFileCropperTranslateMasterTrait;";
        $traitsClass[] = 'use EntityFileCropperTranslateMasterTrait;';
      }
      else
      {
        $useClass[] = "use Austral\EntityTranslateBundle\Entity\Traits\EntityTranslateMasterTrait;";
        $traitsClass[] = 'use EntityTranslateMasterTrait;';
      }
      $useClass[] = "use Austral\EntityTranslateBundle\Attribute\Translate;";
      $useClass[] = "use Doctrine\Common\Collections\Collection;";
      $useClass[] = "use Doctrine\Common\Collections\ArrayCollection;";
      $interfacesClass[] = 'TranslateMasterInterface';
      $attributesClass[] = "#[Translate(relationClass: {$translateName}::class)]";

      $fields[] = '/**
   * @var Collection<int, '.$translateName.'>
   */
  #[ORM\OneToMany(targetEntity: '.$translateName.'::class, mappedBy: \'master\', cascade: [\'persist\', \'remove\'])]
  protected Collection $translates;';
      $construct[] = '$this->translates = new ArrayCollection();';
      $templateParameters['##ENTITY_DEFAULT_FIELD_TO_STRING##'] = 'return $this->getTranslateCurrent() ? $this->getTranslateCurrent()->__toString() : "";';

      $getterSetter[] = '/**
   * @return '.$translateName.'
   * @throws \Exception
   */
  public function createNewTranslateByLanguage(): '.$translateName.'
  {
    $translate = new '.$translateName.'();
    $translate->setMaster($this);
    $translate->setIsReferent(count($this->getTranslatesByLanguage()) > 0);
    $translate->setLanguage($this->getLanguageCurrent());
    $this->addTranslateByLanguage($translate);
    return $translate;
  }';
    }
    elseif ($isTranslate)
    {
      $useClass[] = "use Austral\Contracts\Translate\TranslateMasterInterface;";
      $useClass[] = "use Austral\Contracts\Translate\TranslateChildInterface;";
      $useClass[] = "use Austral\EntityTranslateBundle\Entity\Traits\EntityTranslateChildTrait;";
      $interfacesClass[] = 'TranslateChildInterface';
      $traitsClass[] = 'use EntityTranslateChildTrait;';
      $fields[] = '/**
   * @var '.$masterName.'|TranslateMasterInterface
   */
  #[ORM\ManyToOne(targetEntity: '.$masterName.'::class, inversedBy: \'translates\', cascade: [\'persist\', \'remove\'])]
  #[ORM\JoinColumn(name: \'master_id\', referencedColumnName: \'id\')]
  protected TranslateMasterInterface $master;';
    }

    if ($this->propertiesUsed['blockComponent'])
    {
      $useClass[] = "use Austral\EntityBundle\Entity\Interfaces\ComponentsInterface;";
      $interfacesClass[] = 'ComponentsInterface';

      if ($this->propertiesUsed['translate'] && false === $isTranslate)
      {
        $useClass[] = "use Austral\EntityTranslateBundle\Entity\Traits\EntityTranslateMasterComponentsTrait;";
        $traitsClass[] = 'use EntityTranslateMasterComponentsTrait;';
      }
      else
      {
        $useClass[] = "use Austral\ContentBlockBundle\Entity\Traits\EntityComponentsTrait;";
        $traitsClass[] = 'use EntityComponentsTrait;';
      }
    }

    if (false === $isTranslate)
    {
      if (is_array($this->propertiesUsed['domainFilter']))
      {
        $useClass[] = "use Austral\Contracts\Domain\FilterByDomainInterface;";
        $useClass[] = "use Austral\HttpBundle\Attribute\DomainFilter;";
        $useClass[] = "use Austral\HttpBundle\Entity\Traits\FilterByDomainTrait;";
        $interfacesClass[] = 'FilterByDomainInterface';
        $attributesClass[] = '#[DomainFilter(autoDomainId: '.($this->propertiesUsed['domainFilter']['autoDomainId'] ? 'true' : 'false').', forAllDomainEnabled: '.($this->propertiesUsed['domainFilter']['forAllDomainEnabled'] ? 'true' : 'false').')]';
        $traitsClass[] = 'use FilterByDomainTrait;';
      }

      if ($this->propertiesUsed['file'])
      {
        $useClass[] = "use Austral\Contracts\File\FileInterface;";
        $useClass[] = "use Austral\EntityFileBundle\Entity\Traits\EntityFileTrait;";
        $interfacesClass[] = 'FileInterface';
        $traitsClass[] = 'use EntityFileTrait;';
      }

      // With translations, the master delegates the cropper data to its translation
      // (EntityFileCropperTranslateMasterTrait, added above)
      if ($this->propertiesUsed['cropper'] && !$this->propertiesUsed['translate'])
      {
        $useClass[] = "use Austral\EntityFileBundle\Entity\Interfaces\CropperInterface;";
        $useClass[] = "use Austral\EntityFileBundle\Entity\Traits\EntityFileCropperTrait;";
        $interfacesClass[] = 'CropperInterface';
        $traitsClass[] = 'use EntityFileCropperTrait;';
      }

      if ($this->propertiesUsed['url'])
      {
        $useClass[] = "use Austral\SeoBundle\Attribute\UrlParameterObject;";
        $attributesClass[] = "#[UrlParameterObject(methodGenerateLastPath: 'stringToLastPath')]";
        $getterSetter[] = '/**
   * @return string|null
   * @throws \Exception
   */
  public function stringToLastPath(): ?string
  {
    return $this->__toString();
  }';
        $useClass[] = "use Austral\SeoBundle\Entity\Traits\UrlParameterTrait;";
        $traitsClass[] = 'use UrlParameterTrait;';
      }
      if ($this->propertiesUsed['treePage'])
      {
        $useClass[] = "use Austral\SeoBundle\Entity\Interfaces\TreePageInterface;";
        $useClass[] = "use Austral\SeoBundle\Entity\Traits\TreePageParentTrait;";
        $interfacesClass[] = 'TreePageInterface';
        $traitsClass[] = 'use TreePageParentTrait;';
      }
    }
    elseif ($isTranslate)
    {
      if ($this->propertiesUsed['cropper'])
      {
        $useClass[] = "use Austral\EntityFileBundle\Entity\Interfaces\CropperInterface;";
        $useClass[] = "use Austral\EntityFileBundle\Entity\Traits\EntityFileCropperTrait;";
        $interfacesClass[] = 'CropperInterface';
        $traitsClass[] = 'use EntityFileCropperTrait;';
      }
    }

    if ($this->propertiesUsed['file'])
    {
      // For the file fields: #[AustralFile\Path(...)], #[AustralFile\UploadParameters(...)], ...
      $useClass[] = "use Austral\EntityFileBundle\Attribute as AustralFile;";
    }

    if (count($useClass) > 0)
    {
      $templateParameters['##ENTITY_USE##'] = '
'.implode('
', $useClass);
    }

    if ($interfacesClass)
    {
      $templateParameters['##ENTITY_INTERFACE##'] = ', '.implode(', ', $interfacesClass);
    }

    if ($attributesClass)
    {
      $templateParameters['##ENTITY_ATTRIBUTES##'] = '
'.implode('
', $attributesClass);
    }

    if ($traitsClass)
    {
      $templateParameters['##ENTITY_TRAITS##'] = '
  '.implode('
  ', $traitsClass).'
';
    }

    if ($getterSetter)
    {
      $templateParameters['##ENTITY_GETTER_SETTER##'] = '
  '.implode('

  ', $getterSetter).'
';
    }

    if ($fields)
    {
      $templateParameters['##ENTITY_FIELDS##'] = '
  '.implode('

  ', $fields).'
';
    }

    if ($construct)
    {
      $templateParameters['##ENTITY_CONSTRUCT##'] = implode('
    ', $construct);
    }

    $filePathEntity = "{$this->paths['Entity']}/{$className}.php";
    $fileContent = file_get_contents("{$this->skeletonPath}/Entity.php");
    if (is_string($fileContent))
    {
      $fileContent = str_replace(array_keys($templateParameters), array_values($templateParameters), $fileContent);
      if ($this->writeFile($filePathEntity, "Entity/{$className}.php"))
      {
        file_put_contents($filePathEntity, $fileContent);
      }
    }

    if (false === $isTranslate)
    {
      $filePathEntityManager = "{$this->paths['EntityManager']}/{$className}EntityManager.php";
      $fileContent = file_get_contents("{$this->skeletonPath}/EntityManager.php");
      if (is_string($fileContent))
      {
        $fileContent = str_replace(array_keys($templateParameters), array_values($templateParameters), $fileContent);
        if ($this->writeFile($filePathEntityManager, "EntityManager/{$className}EntityManager.php"))
        {
          file_put_contents($filePathEntityManager, $fileContent);
        }
      }
    }

    $filePathRepository = "{$this->paths['Repository']}/{$className}Repository.php";
    $fileContent = file_get_contents("{$this->skeletonPath}/Repository.php");
    if (is_string($fileContent))
    {
      $fileContent = str_replace(array_keys($templateParameters), array_values($templateParameters), $fileContent);
      if ($this->writeFile($filePathRepository, "Repository/{$className}Repository.php"))
      {
        file_put_contents($filePathRepository, $fileContent);
      }
    }

    return $this;
  }

  /**
   * writeFile
   *
   * @param string $filePath
   * @param string $name
   *
   * @return bool
   */
  public function writeFile(string $filePath, string $name): bool
  {
    if (file_exists($filePath))
    {
      return $this->askConfirmationQuestion("File {$name} already exists, do you want to overwrite the file ? (y|o)");
    }

    return true;
  }

  /**
   * askName
   *
   * @return string
   *
   * @throws \Exception
   */
  protected function askName(): string
  {
    $helper = $this->getHelper('question');
    $name = '';

    if ($helper instanceof QuestionHelper && $this->input && $this->output)
    {
      $question = new Question('Entity Name ? ');
      $question->setNormalizer(function ($value) {
        return $value ? trim($value) : '';
      });

      if (!$name = $helper->ask($this->input, $this->output, $question))
      {
        throw new \Exception('Name is required');
      }
    }

    return $name;
  }

  /**
   * askUsedAustralEntityTranslateBundle
   *
   * @return void
   */
  protected function askUsedAustralEntityTranslateBundle(): void
  {
    $this->askConfirmationQuestion(
      'This Entity is translatable ? (y|o)',
      'translate'
    );
  }

  /**
   * askUsedAustralContentBlockBundle
   *
   * @return void
   */
  protected function askUsedAustralContentBlockBundle(): void
  {
    $this->askConfirmationQuestion(
      'This Entity has BlockComponent ? (y|o)',
      'blockComponent'
    );
  }

  /**
   * askUsedAustralHttpBundle
   *
   * @return void
   */
  protected function askUsedAustralHttpBundle(): void
  {
    if ($this->askConfirmationQuestion('This Entity has a link with domain ? (y|o)'))
    {
      $this->propertiesUsed['domainFilter'] = [
        'forAllDomainEnabled' => false,
        'autoDomainId' => false,
      ];
      $this->propertiesUsed['domainFilter']['forAllDomainEnabled'] = $this->askConfirmationQuestion('All domain is enabled ? (y|o)');
      $this->propertiesUsed['domainFilter']['autoDomainId'] = $this->askConfirmationQuestion('The domain is attached automatically ? (y|o)');
    }
  }

  /**
   * askUsedAustralEntityFileBundle
   *
   * @return void
   */
  protected function askUsedAustralEntityFileBundle(): void
  {
    if ($this->askConfirmationQuestion('This Entity has files ? (y|o)'))
    {
      $this->propertiesUsed['file'] = true;
      $this->propertiesUsed['cropper'] = $this->askConfirmationQuestion('This files use crop ? (y|o)');
    }
  }

  /**
   * askUsedAustralSeoBundle
   *
   * @return void
   */
  protected function askUsedAustralSeoBundle(): void
  {
    if ($this->askConfirmationQuestion('This Entity has a url ? (y|o)'))
    {
      $this->propertiesUsed['url'] = true;
      $this->propertiesUsed['treePage'] = $this->askConfirmationQuestion('This url has a link with WebsitePage ? (y|o)');
    }
  }
}
