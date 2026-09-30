<?php

namespace Tests\Unit;

use App\Services\AccountingEntryEditor;
use PHPUnit\Framework\TestCase;

class AccountingEntryEditorTest extends TestCase
{
    public function test_a_balanced_entry_is_accepted(): void
    {
        $result = AccountingEntryEditor::prepare([
            ['accounting_account_id' => 1, 'debit' => '100.50', 'credit' => '0'],
            ['accounting_account_id' => 2, 'debit' => '0', 'credit' => '100,50'],
        ]);

        $this->assertNull($result['error']);
        $this->assertSame('100.50', $result['lines'][0]['debit']);
        $this->assertSame('100.50', $result['lines'][1]['credit']);
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        $result = AccountingEntryEditor::prepare([
            ['accounting_account_id' => 1, 'debit' => '10', 'credit' => '0'],
            ['accounting_account_id' => 2, 'debit' => '0', 'credit' => '9'],
        ]);

        $this->assertSame('El debe (10,00) y el haber (9,00) no coinciden. No se guardaron los cambios.', $result['error']);
        $this->assertSame([], $result['lines']);
    }

    public function test_a_line_cannot_have_debit_and_credit(): void
    {
        $result = AccountingEntryEditor::prepare([
            ['accounting_account_id' => 1, 'debit' => '10', 'credit' => '10'],
            ['accounting_account_id' => 2, 'debit' => '0', 'credit' => '0'],
        ]);

        $this->assertSame('Una línea no puede tener importe en el debe y en el haber.', $result['error']);
    }

    public function test_blank_rows_are_ignored_and_two_lines_are_required(): void
    {
        $result = AccountingEntryEditor::prepare([
            ['accounting_account_id' => '', 'debit' => '', 'credit' => ''],
            ['accounting_account_id' => 1, 'debit' => '5', 'credit' => '0'],
        ]);

        $this->assertSame('El asiento necesita al menos dos líneas.', $result['error']);
    }
}
