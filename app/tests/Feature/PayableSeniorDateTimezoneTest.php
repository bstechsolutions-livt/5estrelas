<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Services\Senior\PayableMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableSeniorDateTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_preserva_dia_civil_d_m_y_sem_shift(): void
    {
        $mapper = new PayableMapper;
        $header = $mapper->mapHeader([
            'codEmp' => 2,
            'codFil' => 1,
            'numTit' => 'TZ1',
            'codTpt' => 'FAT',
            'codFor' => 1,
            'vlrOri' => 10,
            'vctPro' => '14/07/2026',
            'datEmi' => '13/07/2026',
            'sitTit' => 'AB',
        ]);

        $this->assertSame('2026-07-14', $header['due_date']);
        $this->assertSame('2026-07-13', $header['issue_date']);
    }

    public function test_mapper_preserva_dia_civil_iso_com_z(): void
    {
        $mapper = new PayableMapper;
        $header = $mapper->mapHeader([
            'codEmp' => 2,
            'codFil' => 1,
            'numTit' => 'TZ2',
            'codTpt' => 'FAT',
            'codFor' => 1,
            'vlrOri' => 10,
            'vctPro' => '2026-07-14T00:00:00.000Z',
            'datEmi' => '2026-07-13T03:00:00Z',
            'sitTit' => 'AB',
        ]);

        $this->assertSame('2026-07-14', $header['due_date']);
        $this->assertSame('2026-07-13', $header['issue_date']);
    }

    public function test_json_serializa_due_date_como_ymd_sem_utc_midnight(): void
    {
        $payable = Payable::create([
            'title_number' => 'TZ-JSON',
            'supplier_name' => 'Teste',
            'amount' => 1,
            'due_date' => '2026-07-14',
            'status' => 'pendente',
        ]);

        $json = json_decode($payable->fresh()->toJson(), true);
        $this->assertSame('2026-07-14', $json['due_date']);
        $this->assertStringNotContainsString('T00:00:00', (string) $json['due_date']);
    }
}
