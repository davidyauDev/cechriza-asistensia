<?php

namespace App\Jobs;


use DragonCode\Contracts\Queue\ShouldQueue;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Mail;
use App\Mail\BirthdayGreetingMail;
class SendBirthdayGreetingJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $user;

    private array $usedPhrases = [];

    /**
     * Create a new job instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Lógica para enviar el saludo de cumpleaños al usuario
        // Por ejemplo, enviar un correo electrónico
        if (!isset($this->user->email)) {
            return;
        }
        $url = $this->generateBirthdayCard("{$this->user->first_name} {$this->user->last_name}", $this->user->id);
        if (!$url) {
            return;
        }

        Mail::to($this->user->email)->send(new BirthdayGreetingMail($url));

        //* Once the email is sent, you might want to delete the generated card to save space
        if (file_exists($url)) {
            unlink($url);
        }
    }

    private function randomBrithdayPhrase(): string
    {
        $json = Storage::get('birthday_phrases.json');
        $data = json_decode($json, true);

        $phrases = $data['phrases'];

        $randomPhrase = $phrases[array_rand($phrases)];

        if (count($this->usedPhrases) >= count($phrases)) {
            $this->usedPhrases = [];
        }

        while (in_array($randomPhrase, $this->usedPhrases)) {
            $randomPhrase = $phrases[array_rand($phrases)];
        }

        $this->usedPhrases[] = $randomPhrase;

        return $randomPhrase;
    }


    public function generateBirthdayCard($nombre, $id)
    {
        // Cargar tu plantilla base SIN el recuadro


        try {
            $image = ImageManager::gd()->read(public_path('images/birthday_background2.png'));

            // Agregar el nombre
            $centerX = $image->width() / 2;
            $centerY = 435; // Y donde quieras el texto

            $image->text("{$nombre}", $centerX, $centerY, function (FontFactory $font) {
                $font->size(50);
                $font->file(public_path('fonts/Montserrat-Regular.ttf'));
                $font->color('#1B3A86');
                $font->align('center');
                $font->valign('middle');
            });

            // Agregar la frase de cumpleaños
            $phrase = strtoupper($this->randomBrithdayPhrase());

            $image->text($phrase, $centerX, $centerY + 200, function (FontFactory $font) {
                $font->size(32);
                $font->file(public_path('fonts/Montserrat-Regular.ttf'));
                $font->color('#C69A00'); // dorado
                $font->align('center');
                $font->valign('middle');
                $font->stroke('#FFFFFF', 2); // borde blanco opaco ✔
            });


            // Guardar
            $path = public_path("images/birthday_card_{$id}s.png");
            $image->save($path);




            return $path;
        } catch (Exception $e) {
            ds('Error al generar la tarjeta de cumpleaños: ' . $e->getMessage());
            return null;

        }

    }
    // Lógica para generar 
}