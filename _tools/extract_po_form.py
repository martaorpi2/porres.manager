from pathlib import Path
import re

p = Path(__file__).resolve().parent.parent / 'app/Http/Controllers/Admin/PurchaseRequestCrudController.php'
lines = p.read_text(encoding='utf-8').splitlines(keepends=True)
block_lines = lines[5276:5431]
dedented = ''.join(line[16:] if line.startswith('                ') else line for line in block_lines)

dedented = dedented.replace("if ($entry->status != 'Completada' && $canGenerateOrder) {\n", '', 1)
dedented = dedented.rstrip()
if dedented.endswith('}'):
    dedented = dedented[:-1].rstrip() + '\n'

issue_field = "                    $html .= $this->renderPurchaseOrderIssueDateFieldHtml();\n"
dedented = re.sub(
    r"\$html \.= '<div class=\"row mb-3\">.*?</motion></motion>';\s*",
    issue_field,
    dedented,
    flags=re.DOTALL,
)
dedented = re.sub(
    r"\$html \.= '<div class=\"row mb-3\"><motion class=\"col-md-4\">.*?</motion></motion>';\s*",
    issue_field,
    dedented,
    flags=re.DOTALL,
)
dedented = re.sub(
    r"\$html \.= '<div class=\"row mb-3\"><div class=\"col-md-4\">.*?</div></div>';\s*",
    issue_field,
    dedented,
    flags=re.DOTALL,
)

header = """    private function renderPurchaseOrderIssueDateFieldHtml(): string
    {
        return '<div class=\"row mb-3\"><motion class=\"col-md-4\">'
            .'<label for=\"purchase_order_issue_date\" class=\"form-label\">Fecha de Emisión:</label>'
            .'<input type=\"date\" name=\"issue_date\" id=\"purchase_order_issue_date\" class=\"form-control\" value=\"'.e(date('Y-m-d')).'\" required>'
            .'</div></div>';
    }

    /**
     * Formulario y avisos para generar orden de compra (sección Órdenes de Compra Asociadas).
     */
    private function renderGeneratePurchaseOrderFormHtml(\\App\\Models\\PurchaseRequest $entry): string
    {
        $user = backpack_user();
        if (! $user instanceof \\App\\Models\\User || ! $user->canGeneratePurchaseOrders()) {
            return '';
        }
        if ($entry->status === 'Completada') {
            return '';
        }
        $entry->loadMissing(['marketRates.quoteDetails', 'details', 'details.product']);
        $representanteLegalSinAsignarPorProducto = $user->hasRole('role_representante_legal', 'backpack');
        $totalAmount = $this->recalculateSelectedQuotationsTotalForPurchaseRequest($entry);
        $threshold = \\App\\Models\\PurchaseRequest::quotationCoverageThresholdAmount();
        $minQuotations = \\App\\Models\\PurchaseRequest::minimumQuotationsRequiredAboveThreshold();
        $quotationsCount = $entry->marketRates->count();
        $html = '';

"""

header = header.replace('<motion class', '<div class')

footer = """
        return $html;
    }

"""

Path(__file__).parent.joinpath('_gen_method_snippet.php').write_text(header + dedented + footer, encoding='utf-8')
print('written')
