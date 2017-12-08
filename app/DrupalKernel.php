<?php

namespace Drupal\Core;

use Composer\Autoload\ClassLoader;
use Drupal\Component\Assertion\Handle;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Http\TrustedHostsRequestFactory;
use Drupal\Core\Installer\InstallerRedirectTrait;
use Drupal\Core\Language\Language;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\TestDatabase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Route;

/**
 * The DrupalKernel class is the core of Drupal itself.
 *
 * This class is responsible for building the Dependency Injection Container and
 * also deals with the registration of service providers. It allows registered
 * service providers to add their services to the container. Core provides the
 * CoreServiceProvider, which, in addition to registering any core services that
 * cannot be registered in the core.services.yaml file, adds any compiler passes
 * needed by core, e.g. for processing tagged services. Each module can add its
 * own service provider, i.e. a class implementing
 * Drupal\Core\DependencyInjection\ServiceProvider, to register services to the
 * container, or modify existing services.
 */
class DrupalKernel extends Kernel implements DrupalKernelInterface, TerminableInterface
{
    use InstallerRedirectTrait;

    protected $bootstrapContainerClass = '\Drupal\Component\DependencyInjection\PhpArrayContainer';
    protected $bootstrapContainer;
    protected $prepared = FALSE;
    protected $moduleList;
    protected $moduleData = [];
    protected $classLoader;
    protected $configStorage;
    protected $allowDumping;
    protected $containerNeedsRebuild = FALSE;
    protected $containerNeedsDumping;
    protected $serviceYamls;
    protected $serviceProviderClasses;
    protected $serviceProviders;
    protected static $isEnvironmentInitialized = FALSE;
    protected $sitePath;
    protected $root;

    /**
     * Constructs a DrupalKernel object.
     *
     * @param string $environment
     *   String indicating the environment, e.g. 'prod' or 'dev'.
     * @param $class_loader
     *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
     *   the front controller, but may also be decorated; e.g.,
     *   \Symfony\Component\ClassLoader\ApcClassLoader.
     * @param bool $allow_dumping
     *   (optional) FALSE to stop the container from being written to or read
     *   from disk. Defaults to TRUE.
     * @param string $app_root
     *   (optional) The path to the application root as a string. If not supplied,
     *   the application root will be computed.
     */
    public function __construct($environment, $class_loader, $allow_dumping = TRUE, $app_root = NULL)
    {
        $this->classLoader = $class_loader;
        $this->rootDir = __DIR__;

        if ($app_root === NULL) {
            $app_root = dirname($this->rootDir).'/web';
        }

        $this->root = $app_root;

        parent::__construct($environment, true /* always debug for now */);
    }

    public function registerBundles()
    {
        /*
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Goat\Bundle\GoatBundle(),
            new Goat\AccountBundle\GoatAccountBundle(),
            //new MakinaCorpus\RedisBundle\RedisBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new MakinaCorpus\Calista\CalistaBundle(),
            new AppBundle\AppBundle(),
            new GestionBundle\GestionBundle(),
        ];
        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebServerBundle\WebServerBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }
        return $bundles;
         */

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        /*
        // Reproduce the config_ENV.yml file from Symfony, but keep it
        // optional instead of forcing its usage
        $customConfigFile = $this->rootDir.'/config/config_'.$this->getEnvironment().'.yml';
        if (!file_exists($customConfigFile)) {
            // Else attempt with a default one
            $customConfigFile = $this->rootDir.'/config/config.yml';
        }
        if (!file_exists($customConfigFile)) {
            // If no file is provided by the user, just use the default one
            // that provide sensible defaults for everything to work fine
            $customConfigFile = __DIR__.'/../Resources/config/config.yml';
        }

        $loader->load($customConfigFile);
         */
    }

    /**
     * Returns the appropriate site directory for a request.
     *
     * Multisite is disabled.
     */
    public static function findSitePath(Request $request, $require_settings = TRUE, $app_root = NULL)
    {
        if (static::validateHostname($request) === FALSE) {
            throw new BadRequestHttpException();
        }

        if ($app_root === NULL) {
            $app_root = self::guessApplicationRoot();
        }

        // Check for a simpletest override.
        if ($test_prefix = drupal_valid_test_ua()) {
            $test_db = new TestDatabase($test_prefix);
            return $test_db->getTestSitePath();
        }

        // No multisite
        return 'sites/default';
    }

    /**
     * Determine the application root directory based on assumptions.
     *
     * @return string
     *   The application root.
     */
    protected static function guessApplicationRoot()
    {
        return dirname(__DIR__).'/web';
    }

    /**
     * {@inheritdoc}
     */
    public function setSitePath($path)
    {
        if ($this->booted && $path !== $this->sitePath) {
            throw new \LogicException('Site path cannot be changed after calling boot()');
        }

        $this->sitePath = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getSitePath()
    {
        return $this->sitePath;
    }

    /**
     * {@inheritdoc}
     */
    public function getAppRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectDir()
    {
        return dirname($this->getRootDir());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
      if ($this->booted) {
          return $this;
      }
      if (!$this->sitePath) {
          throw new \Exception('Kernel does not have site path set before calling boot()');
      }

      // Initialize the FileCacheFactory component. We have to do it here instead
      // of in \Drupal\Component\FileCache\FileCacheFactory because we can not use
      // the Settings object in a component.
      $configuration = Settings::get('file_cache');
      FileCacheFactory::setConfiguration($configuration);
      FileCacheFactory::setPrefix(Settings::getApcuPrefix('file_cache', $this->root));

      parent::boot();

      return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getKernelParameters()
    {
        $parameters = parent::getKernelParameters();

        // @todo fixme
        $parameters['default_backend'] = 'pgsql';

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeContainer()
    {
        parent::initializeContainer();

        // Add drupal synthetic services
        $this->attachSynthetic($this->container);

        \Drupal::setContainer($this->container);
    }

    protected function buildContainer()
    {
        $container = parent::buildContainer();
//default_backend
//         $databaseDefinition = new Definition(Connection::class);
//         $databaseDefinition->setFactory('Drupal\Core\Database\Database::getConnection');
//         $databaseDefinition->setArguments(['default']);
//         $databaseDefinition->setPublic(true);
//         $container->addDefinitions(['database' => $databaseDefinition]);

        // Add drupal synthetic services from here, because stupid Drupal is
        // stupid, and some compiler pass will actually attempt to get services
        // which is a very bad use of them.
        $this->attachSynthetic($container);

        return $container;
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = NULL)
    {
        if (isset($this->container)) {
            throw new \Exception('The container should not override an existing container.');
        }
        if ($this->booted) {
            throw new \Exception('The container cannot be set after a booted kernel.');
        }

        $this->container = $container;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCachedContainerDefinition()
    {
        // Use a real Symfony kernel instead.
    }

    /**
     * {@inheritdoc}
     */
    public function loadLegacyIncludes()
    {
        // Done by composer file.
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE)
    {
        // Ensure sane PHP environment variables.
        static::bootEnvironment();
        $this->initializeSettings($request);

        if (!Database::getConnectionInfo() && !drupal_installation_attempted() && PHP_SAPI !== 'cli') {
            throw new \Exception("Uninstalled Drupal, please install using drush");
        } else {
            $response = parent::handle($request);
        }

        // Adapt response headers to the current request.
        $response->prepare($request);

        return $response;
    }

    /**
     * Drupal useless function kept for compabitility
     */
    protected function handleException(\Exception $e, $request, $type)
    {
        throw $e;
    }

    /**
     * Returns service instances to persist from an old container to a new one.
     */
    protected function getServicesToPersist(ContainerInterface $container)
    {
        return []; // Use a real Symfony kernel instead.
    }

    /**
     * Moves persistent service instances into a new container.
     */
    protected function persistServices(ContainerInterface $container, array $persist)
    {
        // Use a real Symfony kernel instead.
    }

    /**
     * {@inheritdoc}
     *
     * This actually is a reduced version of parent::boot() method.
     */
    public function rebuildContainer()
    {
        $this->initializeContainer();

        foreach ($this->getBundles() as $bundle) {
            $bundle->setContainer($this->container);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateContainer()
    {
        // @todo
    }

    /**
     * Compiles a new service container.
     *
     * @return ContainerBuilder The compiled service container
     */
    protected function compileContainer()
    {
        // This should never be called, kept for compatibility
    }

    /**
     * {@inheritdoc}
     *
     * This is where we're going to do black magic, and register Drupal over
     * a complete Symfony kernel. Prey hard, stay strong, don't panic.
     */
    protected function build(ContainerBuilder $container)
    {
        $this->initializeServiceProviders();

        /*
        $container->set('kernel', $this);
         */
        $container->setParameter('container.modules', $this->getModulesParameter());
        $container->setParameter('install_profile', $this->getInstallProfile());

        // Get a list of namespaces and put it onto the container.
        $namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
        // Add all components in \Drupal\Core and \Drupal\Component that have one of
        // the following directories:
        // - Element
        // - Entity
        // - Plugin
        foreach (['Core', 'Component'] as $parent_directory) {
            $path = 'core/lib/Drupal/' . $parent_directory;
            $parent_namespace = 'Drupal\\' . $parent_directory;
            foreach (new \DirectoryIterator($this->root . '/' . $path) as $component) {
                /** @var $component \DirectoryIterator */
                $pathname = $component->getPathname();
                if (!$component->isDot() && $component->isDir() && (is_dir($pathname . '/Plugin') || is_dir($pathname . '/Entity') || is_dir($pathname . '/Element'))) {
                    $namespaces[$parent_namespace . '\\' . $component->getFilename()] = $path . '/' . $component->getFilename();
                }
            }
        }
        $container->setParameter('container.namespaces', $namespaces);

        // Store the default language values on the container. This is so that the
        // default language can be configured using the configuration factory. This
        // avoids the circular dependencies that would created by
        // \Drupal\language\LanguageServiceProvider::alter() and allows the default
        // language to not be English in the installer.
        $default_language_values = Language::$defaultValues;
        if ($system = $this->getConfigStorage()->read('system.site')) {
            if ($default_language_values['id'] != $system['langcode']) {
                $default_language_values = ['id' => $system['langcode']];
            }
        }
        $container->setParameter('language.default_values', $default_language_values);

        // Register synthetic services.
        $container->register('class_loader')->setSynthetic(TRUE);
        $container->register('kernel', 'Symfony\Component\HttpKernel\KernelInterface')->setSynthetic(TRUE);
        $container->register('service_container', 'Symfony\Component\DependencyInjection\ContainerInterface')->setSynthetic(TRUE);

        // Register application services.
        $yaml_loader = new YamlFileLoader($container);
        foreach ($this->serviceYamls['app'] as $filename) {
            $yaml_loader->load($filename);
        }
        foreach ($this->serviceProviders['app'] as $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                $provider->register($container);
            }
        }
        // Register site-specific service overrides.
        foreach ($this->serviceYamls['site'] as $filename) {
            $yaml_loader->load($filename);
        }
        foreach ($this->serviceProviders['site'] as $provider) {
            if ($provider instanceof ServiceProviderInterface) {
                $provider->register($container);
            }
        }

        $container->setParameter('persist_ids', []);
    }

    /**
     * Registers all service providers to the kernel.
     *
     * @throws \LogicException
     */
    protected function initializeServiceProviders()
    {
        $this->discoverServiceProviders();
        $this->serviceProviders = ['app' => [], 'site' => []];

        foreach ($this->serviceProviderClasses as $origin => $classes) {
            foreach ($classes as $name => $class) {
                if (!is_object($class)) {
                    $this->serviceProviders[$origin][$name] = new $class();
                } else {
                    $this->serviceProviders[$origin][$name] = $class;
                }
            }
        }
    }

    /**
     * Stores the container definition in a cache.
     *
     * @param array $container_definition
     *   The container definition to cache.
     *
     * @return bool
     *   TRUE if the container was successfully cached.
     */
    protected function cacheDrupalContainer(array $container_definition)
    {
        return true;
    }

    /**************************************************************************
     *
     * BELOW THIS LINE LIES UNMODIFIED DRUPAL CODE
     *
     *
     **************************************************************************/

  /**
   * Attach synthetic values on to kernel.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *
   * KEPT AS-IS
   */
  protected function attachSynthetic(ContainerInterface $container) {
    $persist = [];
    if (isset($this->container)) {
      $persist = $this->getServicesToPersist($this->container);
    }
    $this->persistServices($container, $persist);

    // All namespaces must be registered before we attempt to use any service
    // from the container.
    $this->classLoaderAddMultiplePsr4($container->getParameter('container.namespaces'));

    $container->set('kernel', $this);

    // Set the class loader which was registered as a synthetic service.
    $container->set('class_loader', $this->classLoader);
    return $container;
  }

  /**
   * Create a DrupalKernel object from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param $class_loader
   *   The class loader. Normally Composer's ClassLoader, as included by the
   *   front controller, but may also be decorated; e.g.,
   *   \Symfony\Component\ClassLoader\ApcClassLoader.
   * @param string $environment
   *   String indicating the environment, e.g. 'prod' or 'dev'.
   * @param bool $allow_dumping
   *   (optional) FALSE to stop the container from being written to or read
   *   from disk. Defaults to TRUE.
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   *
   * @return static
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is not trusted.
   *
   * KEPT AS-IS
   */
  public static function createFromRequest(Request $request, $class_loader, $environment, $allow_dumping = TRUE, $app_root = NULL) {
    $kernel = new static($environment, $class_loader, $allow_dumping, $app_root);
    static::bootEnvironment($app_root);
    $kernel->initializeSettings($request);
    return $kernel;
  }

  /**
   * {@inheritdoc}
   *
   * KEPT AS-IS
   */
  public function preHandle(Request $request)
  {
    // Load all enabled modules.
    $this->container->get('module_handler')->loadAll();

    // Register stream wrappers.
    $this->container->get('stream_wrapper_manager')->register();

    // Initialize legacy request globals.
    $this->initializeRequestGlobals($request);

    // Put the request on the stack.
    $this->container->get('request_stack')->push($request);

    // Set the allowed protocols.
    UrlHelper::setAllowedProtocols($this->container->getParameter('filter_protocols'));

    // Override of Symfony's MIME type guesser singleton.
    MimeTypeGuesser::registerWithSymfonyGuesser($this->container);

    $this->prepared = TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * KEPT AS-IS
   */
  public function discoverServiceProviders()
  {
    // @todo
    $this->serviceYamls = [
      'app' => [],
      'site' => [],
    ];
    $this->serviceProviderClasses = [
      'app' => [],
      'site' => [],
    ];
    $this->serviceYamls['app']['core'] = 'core/core.services.yml';
    $this->serviceProviderClasses['app']['core'] = 'Drupal\Core\CoreServiceProvider';

    // Retrieve enabled modules and register their namespaces.
    if (!isset($this->moduleList)) {
      $extensions = $this->getConfigStorage()->read('core.extension');
      $this->moduleList = isset($extensions['module']) ? $extensions['module'] : [];
    }
    $module_filenames = $this->getModuleFileNames();
    $this->classLoaderAddMultiplePsr4($this->getModuleNamespacesPsr4($module_filenames));

    // Load each module's serviceProvider class.
    foreach ($module_filenames as $module => $filename) {
      $camelized = ContainerBuilder::camelize($module);
      $name = "{$camelized}ServiceProvider";
      $class = "Drupal\\{$module}\\{$name}";
      if (class_exists($class)) {
        $this->serviceProviderClasses['app'][$module] = $class;
      }
      $filename = dirname($filename) . "/$module.services.yml";
      if (file_exists($filename)) {
        $this->serviceYamls['app'][$module] = $filename;
      }
    }

    // Add site-specific service providers.
    if (!empty($GLOBALS['conf']['container_service_providers'])) {
      foreach ($GLOBALS['conf']['container_service_providers'] as $class) {
        if ((is_string($class) && class_exists($class)) || (is_object($class) && ($class instanceof ServiceProviderInterface || $class instanceof ServiceModifierInterface))) {
          $this->serviceProviderClasses['site'][] = $class;
        }
      }
    }
    $this->addServiceFiles(Settings::get('container_yamls', []));
  }

  /**
   * {@inheritdoc}
   *
   * KEPT AS-IS
   */
  public function getServiceProviders($origin)
  {
    return $this->serviceProviders[$origin];
  }

  /**
   * {@inheritdoc}
   *
   * KEPT AS-IS
   */
  public function prepareLegacyRequest(Request $request) {
    $this->boot();
    $this->preHandle($request);
    // Setup services which are normally initialized from within stack
    // middleware or during the request kernel event.
    if (PHP_SAPI !== 'cli') {
      $request->setSession($this->container->get('session'));
    }
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('<none>'));
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, '<none>');
    $this->container->get('request_stack')->push($request);
    $this->container->get('router.request_context')->fromRequest($request);
    return $this;
  }

  /**
   * Returns module data on the filesystem.
   *
   * @param $module
   *   The name of the module.
   *
   * @return \Drupal\Core\Extension\Extension|bool
   *   Returns an Extension object if the module is found, FALSE otherwise.
   *
   * KEPT AS-IS
   */
  protected function moduleData($module) {
    if (!$this->moduleData) {
      // First, find profiles.
      $listing = new ExtensionDiscovery($this->root);
      $listing->setProfileDirectories([]);
      $all_profiles = $listing->scan('profile');
      $profiles = array_intersect_key($all_profiles, $this->moduleList);

      // If a module is within a profile directory but specifies another
      // profile for testing, it needs to be found in the parent profile.
      $settings = $this->getConfigStorage()->read('simpletest.settings');
      $parent_profile = !empty($settings['parent_profile']) ? $settings['parent_profile'] : NULL;
      if ($parent_profile && !isset($profiles[$parent_profile])) {
        // In case both profile directories contain the same extension, the
        // actual profile always has precedence.
        $profiles = [$parent_profile => $all_profiles[$parent_profile]] + $profiles;
      }

      $profile_directories = array_map(function ($profile) {
        return $profile->getPath();
      }, $profiles);
      $listing->setProfileDirectories($profile_directories);

      // Now find modules.
      $this->moduleData = $profiles + $listing->scan('module');
    }
    return isset($this->moduleData[$module]) ? $this->moduleData[$module] : FALSE;
  }

  /**
   * Implements Drupal\Core\DrupalKernelInterface::updateModules().
   *
   * @todo Remove obsolete $module_list parameter. Only $module_filenames is
   *   needed.
   *
   * KEPT AS-IS
   */
  public function updateModules(array $module_list, array $module_filenames = []) {
    $pre_existing_module_namespaces = [];
    if ($this->booted && is_array($this->moduleList)) {
      $pre_existing_module_namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
    }
    $this->moduleList = $module_list;
    foreach ($module_filenames as $name => $extension) {
      $this->moduleData[$name] = $extension;
    }

    // If we haven't yet booted, we don't need to do anything: the new module
    // list will take effect when boot() is called. However we set a
    // flag that the container needs a rebuild, so that a potentially cached
    // container is not used. If we have already booted, then rebuild the
    // container in order to refresh the serviceProvider list and container.
    $this->containerNeedsRebuild = TRUE;
    if ($this->booted) {
      // We need to register any new namespaces to a new class loader because
      // the current class loader might have stored a negative result for a
      // class that is now available.
      // @see \Composer\Autoload\ClassLoader::findFile()
      $new_namespaces = array_diff_key(
        $this->getModuleNamespacesPsr4($this->getModuleFileNames()),
        $pre_existing_module_namespaces
      );
      if (!empty($new_namespaces)) {
        $additional_class_loader = new ClassLoader();
        $this->classLoaderAddMultiplePsr4($new_namespaces, $additional_class_loader);
        $additional_class_loader->register();
      }

      $this->initializeContainer();
    }
  }

  /**
   * Returns the container cache key based on the environment.
   *
   * The 'environment' consists of:
   * - The kernel environment string.
   * - The Drupal version constant.
   * - The deployment identifier from settings.php. This allows custom
   *   deployments to force a container rebuild.
   * - The operating system running PHP. This allows compiler passes to optimize
   *   services for different operating systems.
   * - The paths to any additional container YAMLs from settings.php.
   *
   * @return string
   *   The cache key used for the service container.
   *
   * KEPT AS-IS
   */
  protected function getContainerCacheKey() {
    $parts = ['service_container', $this->environment, \Drupal::VERSION, Settings::get('deployment_identifier'), PHP_OS, serialize(Settings::get('container_yamls'))];
    return implode(':', $parts);
  }

  /**
   * Setup a consistent PHP environment.
   *
   * This method sets PHP environment options we want to be sure are set
   * correctly for security or just saneness.
   *
   * @param string $app_root
   *   (optional) The path to the application root as a string. If not supplied,
   *   the application root will be computed.
   *
   * KEPT AS-IS
   */
  public static function bootEnvironment($app_root = NULL) {
    if (static::$isEnvironmentInitialized) {
      return;
    }

    // Determine the application root if it's not supplied.
    if ($app_root === NULL) {
      $app_root = static::guessApplicationRoot();
    }

    // Include our bootstrap file.
    require_once $app_root . '/core/includes/bootstrap.inc';

    // Enforce E_STRICT, but allow users to set levels not part of E_STRICT.
    error_reporting(E_STRICT | E_ALL);

    // Override PHP settings required for Drupal to work properly.
    // sites/default/default.settings.php contains more runtime settings.
    // The .htaccess file contains settings that cannot be changed at runtime.

    // Use session cookies, not transparent sessions that puts the session id in
    // the query string.
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Don't send HTTP headers using PHP's session handler.
    // Send an empty string to disable the cache limiter.
    ini_set('session.cache_limiter', '');
    // Use httponly session cookies.
    ini_set('session.cookie_httponly', '1');

    // Set sane locale settings, to ensure consistent string, dates, times and
    // numbers handling.
    setlocale(LC_ALL, 'C');

    // Detect string handling method.
    Unicode::check();

    // Indicate that code is operating in a test child site.
    if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
      if ($test_prefix = drupal_valid_test_ua()) {
        $test_db = new TestDatabase($test_prefix);
        // Only code that interfaces directly with tests should rely on this
        // constant; e.g., the error/exception handler conditionally adds further
        // error information into HTTP response headers that are consumed by
        // Simpletest's internal browser.
        define('DRUPAL_TEST_IN_CHILD_SITE', TRUE);

        // Web tests are to be conducted with runtime assertions active.
        assert_options(ASSERT_ACTIVE, TRUE);
        // Now synchronize PHP 5 and 7's handling of assertions as much as
        // possible.
        Handle::register();

        // Log fatal errors to the test site directory.
        ini_set('log_errors', 1);
        ini_set('error_log', $app_root . '/' . $test_db->getTestSitePath() . '/error.log');

        // Ensure that a rewritten settings.php is used if opcache is on.
        ini_set('opcache.validate_timestamps', 'on');
        ini_set('opcache.revalidate_freq', 0);
      }
      else {
        // Ensure that no other code defines this.
        define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
      }
    }

    // Set the Drupal custom error handler.
    set_error_handler('_drupal_error_handler');
    set_exception_handler('_drupal_exception_handler');

    static::$isEnvironmentInitialized = TRUE;
  }

  /**
   * Locate site path and initialize settings singleton.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   In case the host name in the request is not trusted.
   *
   * KEPT AS-IS
   */
  protected function initializeSettings(Request $request) {
    $site_path = static::findSitePath($request);
    $this->setSitePath($site_path);
    $class_loader_class = get_class($this->classLoader);
    Settings::initialize($this->root, $site_path, $this->classLoader);

    // Initialize our list of trusted HTTP Host headers to protect against
    // header attacks.
    $host_patterns = Settings::get('trusted_host_patterns', []);
    if (PHP_SAPI !== 'cli' && !empty($host_patterns)) {
      if (static::setupTrustedHosts($request, $host_patterns) === FALSE) {
        throw new BadRequestHttpException('The provided host name is not valid for this server.');
      }
    }

    // If the class loader is still the same, possibly
    // upgrade to an optimized class loader.
    if ($class_loader_class == get_class($this->classLoader)
        && Settings::get('class_loader_auto_detect', TRUE)) {
      $prefix = Settings::getApcuPrefix('class_loader', $this->root);
      $loader = NULL;

      if (!empty($loader)) {
        $this->classLoader->unregister();
        // The optimized classloader might be persistent and store cache misses.
        // For example, once a cache miss is stored in APCu clearing it on a
        // specific web-head will not clear any other web-heads. Therefore
        // fallback to the composer class loader that only statically caches
        // misses.
        $old_loader = $this->classLoader;
        $this->classLoader = $loader;
        // Our class loaders are preprended to ensure they come first like the
        // class loader they are replacing.
        $old_loader->register(TRUE);
        $loader->register(TRUE);
      }
    }
  }

  /**
   * Bootstraps the legacy global request variables.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @todo D8: Eliminate this entirely in favor of Request object.
   *
   * KEPT AS-IS
   */
  protected function initializeRequestGlobals(Request $request) {
    global $base_url;
    // Set and derived from $base_url by this function.
    global $base_path, $base_root;
    global $base_secure_url, $base_insecure_url;

    // Create base URL.
    $base_root = $request->getSchemeAndHttpHost();
    $base_url = $base_root;

    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
      // Remove "core" directory if present, allowing install.php,
      // authorize.php, and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
    $base_secure_url = str_replace('http://', 'https://', $base_url);
    $base_insecure_url = str_replace('https://', 'http://', $base_url);
  }

  /**
   * Returns the active configuration storage to use during building the container.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *
   * KEPT AS-IS
   */
  protected function getConfigStorage() {
    if (!isset($this->configStorage)) {
      // The active configuration storage may not exist yet; e.g., in the early
      // installer. Catch the exception thrown by config_get_config_directory().
      try {
        $this->configStorage = BootstrapConfigStorageFactory::get($this->classLoader);
      }
      catch (\Exception $e) {
        $this->configStorage = new NullStorage();
      }
    }
    return $this->configStorage;
  }

  /**
   * Returns an array of Extension class parameters for all enabled modules.
   *
   * @return array
   *
   * KEPT AS-IS
   */
  protected function getModulesParameter() {
    $extensions = [];
    foreach ($this->moduleList as $name => $weight) {
      if ($data = $this->moduleData($name)) {
        $extensions[$name] = [
          'type' => $data->getType(),
          'pathname' => $data->getPathname(),
          'filename' => $data->getExtensionFilename(),
        ];
      }
    }
    return $extensions;
  }

  /**
   * Gets the file name for each enabled module.
   *
   * @return array
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.info.yml file.
   *
   * KEPT AS-IS
   */
  protected function getModuleFileNames() {
    $filenames = [];
    foreach ($this->moduleList as $module => $weight) {
      if ($data = $this->moduleData($module)) {
        $filenames[$module] = $data->getPathname();
      }
    }
    return $filenames;
  }

  /**
   * Gets the PSR-4 base directories for module namespaces.
   *
   * @param string[] $module_file_names
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.info.yml file.
   *
   * @return string[]
   *   Array where each key is a module namespace like 'Drupal\system', and each
   *   value is the PSR-4 base directory associated with the module namespace.
   *
   * KEPT AS-IS
   */
  protected function getModuleNamespacesPsr4($module_file_names) {
    $namespaces = [];
    foreach ($module_file_names as $module => $filename) {
      $namespaces["Drupal\\$module"] = dirname($filename) . '/src';
    }
    return $namespaces;
  }

  /**
   * Registers a list of namespaces with PSR-4 directories for class loading.
   *
   * @param array $namespaces
   *   Array where each key is a namespace like 'Drupal\system', and each value
   *   is either a PSR-4 base directory, or an array of PSR-4 base directories
   *   associated with this namespace.
   * @param object $class_loader
   *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
   *   the front controller, but may also be decorated; e.g.,
   *   \Symfony\Component\ClassLoader\ApcClassLoader.
   *
   * KEPT AS-IS
   */
  protected function classLoaderAddMultiplePsr4(array $namespaces = [], $class_loader = NULL) {
    if ($class_loader === NULL) {
      $class_loader = $this->classLoader;
    }
    foreach ($namespaces as $prefix => $paths) {
      if (is_array($paths)) {
        foreach ($paths as $key => $value) {
          $paths[$key] = $this->root . '/' . $value;
        }
      }
      elseif (is_string($paths)) {
        $paths = $this->root . '/' . $paths;
      }
      $class_loader->addPsr4($prefix . '\\', $paths);
    }
  }

  /**
   * Validates a hostname length.
   *
   * @param string $host
   *   A hostname.
   *
   * @return bool
   *   TRUE if the length is appropriate, or FALSE otherwise.
   *
   * KEPT AS-IS
   */
  protected static function validateHostnameLength($host) {
    // Limit the length of the host name to 1000 bytes to prevent DoS attacks
    // with long host names.
    return strlen($host) <= 1000
    // Limit the number of subdomains and port separators to prevent DoS attacks
    // in findSitePath().
    && substr_count($host, '.') <= 100
    && substr_count($host, ':') <= 100;
  }

  /**
   * Validates the hostname supplied from the HTTP request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object
   *
   * @return bool
   *   TRUE if the hostname is valid, or FALSE otherwise.
   *
   * KEPT AS-IS
   */
  public static function validateHostname(Request $request) {
    // $request->getHost() can throw an UnexpectedValueException if it
    // detects a bad hostname, but it does not validate the length.
    try {
      $http_host = $request->getHost();
    }
    catch (\UnexpectedValueException $e) {
      return FALSE;
    }

    if (static::validateHostnameLength($http_host) === FALSE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sets up the lists of trusted HTTP Host headers.
   *
   * Since the HTTP Host header can be set by the user making the request, it
   * is possible to create an attack vectors against a site by overriding this.
   * Symfony provides a mechanism for creating a list of trusted Host values.
   *
   * Host patterns (as regular expressions) can be configured through
   * settings.php for multisite installations, sites using ServerAlias without
   * canonical redirection, or configurations where the site responds to default
   * requests. For example,
   *
   * @code
   * $settings['trusted_host_patterns'] = array(
   *   '^example\.com$',
   *   '^*.example\.com$',
   * );
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $host_patterns
   *   The array of trusted host patterns.
   *
   * @return bool
   *   TRUE if the Host header is trusted, FALSE otherwise.
   *
   * @see https://www.drupal.org/node/1992030
   * @see \Drupal\Core\Http\TrustedHostsRequestFactory
   *
   * KEPT AS-IS
   */
  protected static function setupTrustedHosts(Request $request, $host_patterns) {
    $request->setTrustedHosts($host_patterns);

    // Get the host, which will validate the current request.
    try {
      $host = $request->getHost();

      // Fake requests created through Request::create() without passing in the
      // server variables from the main request have a default host of
      // 'localhost'. If 'localhost' does not match any of the trusted host
      // patterns these fake requests would fail the host verification. Instead,
      // TrustedHostsRequestFactory makes sure to pass in the server variables
      // from the main request.
      $request_factory = new TrustedHostsRequestFactory($host);
      Request::setFactory([$request_factory, 'createRequest']);

    }
    catch (\UnexpectedValueException $e) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Add service files.
   *
   * @param string[] $service_yamls
   *   A list of service files.
   *
   * KEPT AS-IS
   */
  protected function addServiceFiles(array $service_yamls) {
    $this->serviceYamls['site'] = array_filter($service_yamls, 'file_exists');
  }

  /**
   * Gets the active install profile.
   *
   * @return string|null
   *   The name of the any active install profile or distribution.
   *
   * KEPT AS-IS
   */
  protected function getInstallProfile() {
    $config = $this->getConfigStorage()->read('core.extension');
    if (!empty($config['profile'])) {
      $install_profile = $config['profile'];
    }
    // @todo https://www.drupal.org/node/2831065 remove the BC layer.
    else {
      // If system_update_8300() has not yet run fallback to using settings.
      $install_profile = Settings::get('install_profile');
    }

    // Normalize an empty string to a NULL value.
    return empty($install_profile) ? NULL : $install_profile;
  }
}
