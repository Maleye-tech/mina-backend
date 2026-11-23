<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerDocsTest extends TestCase
{
    public function test_api_documentation_ui_is_available(): void
    {
        $this->get('/api/documentation')->assertOk();
    }

    public function test_openapi_document_exposes_the_api_contract(): void
    {
        $document = $this->getJson('/docs')->assertOk()->json();

        $this->assertSame('3.0.0', $document['openapi']);
        $this->assertSame('Mina API', $document['info']['title']);
        $this->assertSame([['url' => config('app.url').'/api']], $document['servers']);
        $this->assertArrayHasKey('sanctum', $document['components']['securitySchemes']);
        $this->assertArrayHasKey('User', $document['components']['schemas']);
    }

    public function test_every_api_route_is_documented(): void
    {
        $document = $this->getJson('/docs')->assertOk()->json();

        $operations = 0;
        foreach ($document['paths'] as $path => $item) {
            $this->assertStringStartsWith('/', $path);

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (! isset($item[$method])) {
                    continue;
                }

                $operations++;
                $operation = $item[$method];

                $this->assertNotEmpty($operation['responses'] ?? [], "Aucune réponse pour $method $path");
                $this->assertNotEmpty($operation['operationId'] ?? '', "Aucun operationId pour $method $path");

                $hasToken = isset($operation['security']);
                $this->assertSame(
                    $hasToken,
                    isset($operation['responses']['401']),
                    "401 et security incohérents pour $method $path"
                );

                if (preg_match_all('/\{(\w+)\}/', $path, $matches)) {
                    $declared = array_column(
                        array_filter($operation['parameters'] ?? [], fn (array $p): bool => ($p['in'] ?? null) === 'path'),
                        'name'
                    );
                    foreach ($matches[1] as $name) {
                        $this->assertContains($name, $declared, "Paramètre $name manquant pour $method $path");
                    }
                }
            }
        }

        $this->assertGreaterThanOrEqual(70, count($document['paths']));
        $this->assertGreaterThanOrEqual(110, $operations);
    }

    public function test_manually_annotated_operations_are_kept(): void
    {
        $document = $this->getJson('/docs')->assertOk()->json();

        $login = $document['paths']['/auth/login']['post'] ?? [];

        $this->assertSame('Se connecter', $login['summary'] ?? null);
        $this->assertStringContainsString(
            '#/components/schemas/User',
            json_encode($login, JSON_UNESCAPED_SLASHES)
        );

        $register = $document['paths']['/auth/register']['post'] ?? [];

        $this->assertSame('Créer un compte étudiant', $register['summary'] ?? null);
        $this->assertSame(
            ['name', 'email', 'password', 'password_confirmation'],
            $register['requestBody']['content']['application/json']['schema']['required'] ?? []
        );
    }
}
