<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BusquedaWebService;
use Tests\TestCase;

class BusquedaWebServiceTest extends TestCase
{
    private BusquedaWebService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusquedaWebService();
    }

    public function test_detecta_temas_volatiles_y_de_actualidad(): void
    {
        $this->assertTrue($this->service->esTemaActualOVolatil('El precio del dólar hoy cerró en 4.250 pesos en Colombia'));
        $this->assertTrue($this->service->esTemaActualOVolatil('El barril de petróleo Brent cotiza a 75 dólares'));
        $this->assertTrue($this->service->esTemaActualOVolatil('La acción de Ecopetrol cayó en la bolsa de valores'));
        $this->assertTrue($this->service->esTemaActualOVolatil('La inflación IPC en Colombia bajó este mes'));
        $this->assertTrue($this->service->esTemaActualOVolatil('El presidente nombró un nuevo ministro hoy'));
    }

    public function test_no_marca_como_volatiles_temas_historicos_estables(): void
    {
        $this->assertFalse($this->service->esTemaActualOVolatil('La capital de Colombia es Bogotá'));
        $this->assertFalse($this->service->esTemaActualOVolatil('Gabriel García Márquez ganó el Premio Nobel de Literatura en 1982'));
    }
}
