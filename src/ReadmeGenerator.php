<?php

declare(strict_types=1);

namespace Gonzalolopezgonzalez\McpReadmeGenerator;

use Mcp\Capability\Attribute\McpTool;

/**
 * Clase que contiene las tools del MCP.
 * 
 * Cada método marcado con #[McpTool] se convierte automáticamente
 * en una herramienta que el LLM (Claude, Cursor, etc.) puede llamar.
 */
class ReadmeGenerator
{
    /**
     * Analiza un proyecto PHP y extrae información útil para generar un README.
     *
     * Esta tool escanea el directorio indicado y devuelve datos estructurados
     * (composer.json, estructura de carpetas, framework detectado, etc.).
     *
     * @param string $path Ruta al proyecto (por defecto el directorio actual)
     * @return array Información estructurada del proyecto
     */
    #[McpTool]
    public function analyzeProject(string $path = '.'): array
    {
        $path = realpath($path) ?: $path;

        if (!is_dir($path)) {
            throw new \InvalidArgumentException("La ruta no es un directorio válido: $path");
        }

        $result = [
            'path' => $path,
            'name' => basename($path),
            'has_composer' => false,
            'composer' => null,
            'framework' => 'PHP genérico',
            'directories' => [],
            'important_files' => [],
            'has_tests' => false,
            'has_readme' => file_exists($path . '/README.md'),
        ];

        // Leer composer.json si existe
        $composerFile = $path . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $result['has_composer'] = true;
            $result['composer'] = [
                'name' => $composer['name'] ?? null,
                'description' => $composer['description'] ?? null,
                'type' => $composer['type'] ?? null,
                'license' => $composer['license'] ?? null,
                'authors' => $composer['authors'] ?? [],
                'require' => $composer['require'] ?? [],
                'require-dev' => $composer['require-dev'] ?? [],
                'scripts' => $composer['scripts'] ?? [],
            ];

            // Detectar framework
            $require = array_merge(
                array_keys($composer['require'] ?? []),
                array_keys($composer['require-dev'] ?? [])
            );

            if (in_array('laravel/framework', $require)) {
                $result['framework'] = 'Laravel';
            } elseif (in_array('symfony/framework-bundle', $require) || in_array('symfony/symfony', $require)) {
                $result['framework'] = 'Symfony';
            } elseif (in_array('slim/slim', $require)) {
                $result['framework'] = 'Slim';
            } elseif (in_array('cakephp/cakephp', $require)) {
                $result['framework'] = 'CakePHP';
            }
        }

        // Listar directorios importantes
        $importantDirs = ['src', 'app', 'tests', 'config', 'public', 'resources', 'database', 'routes', 'storage'];
        foreach ($importantDirs as $dir) {
            if (is_dir($path . '/' . $dir)) {
                $result['directories'][] = $dir;
            }
        }

        // Archivos importantes
        $importantFiles = [
            'composer.json', '.env.example', 'phpunit.xml', 'phpunit.xml.dist',
            'pest.php', 'artisan', 'bin/console', 'public/index.php', 'index.php'
        ];
        foreach ($importantFiles as $file) {
            if (file_exists($path . '/' . $file)) {
                $result['important_files'][] = $file;
            }
        }

        // Detectar tests
        $result['has_tests'] = in_array('tests', $result['directories']) 
            || file_exists($path . '/phpunit.xml') 
            || file_exists($path . '/phpunit.xml.dist')
            || file_exists($path . '/pest.php');

        return $result;
    }

    /**
     * Genera un README.md profesional a partir del análisis del proyecto.
     *
     * @param string $path          Ruta al proyecto
     * @param string $language      Idioma del README (es o en)
     * @param string $style         Estilo: professional | casual | minimal
     * @param bool   $includeBadges Incluir badges de Packagist/GitHub
     * @param string|null $extraInfo Información adicional que quieras incluir
     * @return string Contenido completo del README en Markdown
     */
    #[McpTool]
    public function generateReadme(
        string $path = '.',
        string $language = 'es',
        string $style = 'professional',
        bool $includeBadges = true,
        ?string $extraInfo = null
    ): string {
        $data = $this->analyzeProject($path);

        $isSpanish = strtolower($language) === 'es';
        $name = $data['composer']['name'] ?? $data['name'];
        $description = $data['composer']['description'] ?? ($isSpanish 
            ? 'Proyecto PHP' 
            : 'PHP Project');

        $md = [];

        // Título
        $md[] = "# " . ($data['composer']['name'] ?? ucfirst($data['name']));
        $md[] = "";

        // Badges (opcional)
        if ($includeBadges && $data['has_composer'] && isset($data['composer']['name'])) {
            $package = $data['composer']['name'];
            $md[] = "![Packagist Version](https://img.shields.io/packagist/v/{$package})";
            $md[] = "![License](https://img.shields.io/packagist/l/{$package})";
            $md[] = "";
        }

        // Descripción
        $md[] = $description;
        $md[] = "";

        if ($extraInfo) {
            $md[] = $extraInfo;
            $md[] = "";
        }

        // Framework
        if ($data['framework'] !== 'PHP genérico') {
            $md[] = $isSpanish 
                ? "**Framework:** {$data['framework']}" 
                : "**Framework:** {$data['framework']}";
            $md[] = "";
        }

        // Requisitos
        $md[] = $isSpanish ? "## Requisitos" : "## Requirements";
        $md[] = "";
        $md[] = "- PHP >= 8.1";
        if ($data['has_composer']) {
            $md[] = "- Composer";
        }
        $md[] = "";

        // Instalación
        $md[] = $isSpanish ? "## Instalación" : "## Installation";
        $md[] = "";
        $md[] = "```bash";
        $md[] = "composer install";
        $md[] = "```";
        $md[] = "";

        // Uso / Scripts
        if (!empty($data['composer']['scripts'])) {
            $md[] = $isSpanish ? "## Scripts disponibles" : "## Available Scripts";
            $md[] = "";
            foreach ($data['composer']['scripts'] as $script => $command) {
                $md[] = "- `composer $script`";
            }
            $md[] = "";
        }

        // Estructura
        if (!empty($data['directories'])) {
            $md[] = $isSpanish ? "## Estructura del proyecto" : "## Project Structure";
            $md[] = "";
            $md[] = "```";
            foreach ($data['directories'] as $dir) {
                $md[] = "├── $dir/";
            }
            $md[] = "```";
            $md[] = "";
        }

        // Tests
        if ($data['has_tests']) {
            $md[] = $isSpanish ? "## Tests" : "## Testing";
            $md[] = "";
            $md[] = "```bash";
            $md[] = "composer test";
            $md[] = "# o";
            $md[] = "./vendor/bin/phpunit";
            $md[] = "```";
            $md[] = "";
        }

        // Licencia
        $license = $data['composer']['license'] ?? 'MIT';
        $md[] = $isSpanish ? "## Licencia" : "## License";
        $md[] = "";
        $md[] = "Este proyecto está bajo la licencia **$license**.";
        $md[] = "";

        return implode("\n", $md);
    }

    /**
     * Guarda el contenido generado en un archivo README.md.
     *
     * Por seguridad solo permite escribir dentro del directorio del proyecto
     * y solo archivos llamados README.md.
     *
     * @param string $content Contenido Markdown
     * @param string $path    Ruta donde guardar (por defecto ./README.md)
     * @return string Mensaje de confirmación
     */
    #[McpTool]
    public function saveReadme(string $content, string $path = './README.md'): string
    {
        $path = realpath(dirname($path)) . '/' . basename($path);

        // Seguridad básica: solo permitir README.md
        if (basename($path) !== 'README.md') {
            throw new \InvalidArgumentException("Solo se permite guardar archivos llamados README.md");
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("No se pudo escribir el archivo: $path");
        }

        return "README.md guardado correctamente en: $path";
    }
}
