<?php

/*
 * This file is part of the Austral Maker Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\MakerBundle\Tests\Command;

use Austral\MakerBundle\Command\MakeEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class MakeEntityTest extends TestCase
{
  private string $directory;

  protected function setUp(): void
  {
    $this->directory = sys_get_temp_dir().'/austral-maker-'.bin2hex(random_bytes(4));
  }

  protected function tearDown(): void
  {
    new Filesystem()->remove($this->directory);
  }

  /**
   * @param array<string, mixed> $options
   */
  private function runCommand(array $options): CommandTester
  {
    $command = new MakeEntity('austral:make:entity');
    $command->setApplication(new Application());
    $tester = new CommandTester($command);
    $tester->execute(array_merge(['--path-file' => $this->directory, '--namespace' => 'App'], $options), ['interactive' => false]);

    return $tester;
  }

  private function read(string $path): string
  {
    $file = "{$this->directory}/{$path}";
    self::assertFileExists($file);
    $content = (string) file_get_contents($file);
    // The generated code must be valid PHP
    // TOKEN_PARSE throws a \ParseError when the code is not valid
    self::assertNotEmpty(token_get_all($content, TOKEN_PARSE));

    return $content;
  }

  public function testGeneratesAnEntityWithAttributes(): void
  {
    $tester = $this->runCommand(['name' => 'blog_post']);
    self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

    $entity = $this->read('Entity/BlogPost.php');
    self::assertStringStartsWith('<?php', $entity);
    self::assertStringContainsString('namespace App\Entity;', $entity);
    self::assertStringContainsString('class BlogPost extends Entity implements EntityInterface', $entity);
    self::assertStringContainsString("#[ORM\\Table(name: 'app_blog_post')]", $entity);
    self::assertStringContainsString('#[ORM\Entity(repositoryClass: BlogPostRepository::class)]', $entity);
    self::assertStringContainsString('use App\Repository\BlogPostRepository;', $entity);
    self::assertStringContainsString('protected string $id;', $entity);
    self::assertStringNotContainsString('@ORM', $entity);

    $entityManager = $this->read('EntityManager/BlogPostEntityManager.php');
    self::assertStringContainsString('class BlogPostEntityManager extends EntityManager', $entityManager);
    self::assertStringNotContainsString('protected $repository', $entityManager);

    $repository = $this->read('Repository/BlogPostRepository.php');
    self::assertStringContainsString('class BlogPostRepository extends EntityRepository', $repository);
  }

  public function testGeneratesTheTranslationEntityWithAllProperties(): void
  {
    $tester = $this->runCommand(['name' => 'blog_post', '--all-properties' => true]);
    self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

    $master = $this->read('Entity/BlogPost.php');
    self::assertStringContainsString('#[Translate(relationClass: BlogPostTranslate::class)]', $master);
    self::assertStringContainsString('#[DomainFilter(autoDomainId: true, forAllDomainEnabled: true)]', $master);
    self::assertStringContainsString("#[UrlParameterObject(methodGenerateLastPath: 'stringToLastPath')]", $master);
    self::assertStringContainsString("#[ORM\\OneToMany(targetEntity: BlogPostTranslate::class, mappedBy: 'master'", $master);
    self::assertStringContainsString('public function createNewTranslateByLanguage(): BlogPostTranslate', $master);
    // With translations, the cropper trait of the master already includes EntityTranslateMasterTrait
    self::assertStringContainsString('use EntityFileCropperTranslateMasterTrait;', $master);
    self::assertStringNotContainsString('use EntityTranslateMasterTrait;', $master);
    self::assertStringContainsString('FilterByDomainInterface', $master);
    self::assertStringNotContainsString('Annotation', $master);

    $translate = $this->read('Entity/BlogPostTranslate.php');
    self::assertStringContainsString('class BlogPostTranslate extends Entity implements EntityInterface, TranslateChildInterface', $translate);
    self::assertStringContainsString("#[ORM\\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'translates'", $translate);
    self::assertStringContainsString('use EntityFileCropperTrait;', $translate);
    self::assertStringContainsString('CropperInterface', $translate);

    self::assertFileExists("{$this->directory}/Repository/BlogPostTranslateRepository.php");
    self::assertFileDoesNotExist("{$this->directory}/EntityManager/BlogPostTranslateEntityManager.php");
  }
}
