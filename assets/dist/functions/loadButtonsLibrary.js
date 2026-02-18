import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';
import 'datatables.net-buttons/js/buttons.print';
import JSZip from 'jszip';
import pdfMake from 'pdfmake';
import 'pdfmake/build/vfs_fonts';
export async function loadButtonsLibrary(DataTable, stylesheet) {
    ;
    (await import('datatables.net-buttons')).default;
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        ;
        (await import('datatables.net-buttons-bs5')).default;
    }
    else {
        ;
        (await import('datatables.net-buttons-dt')).default;
    }
    DataTable.Buttons.jszip(JSZip);
    DataTable.Buttons.pdfMake(pdfMake);
}
//# sourceMappingURL=loadButtonsLibrary.js.map