<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Feliz Cumpleaños</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background: #f0f0f0;
        }

        .card-container {
            width: 100%;
            height: auto;
            margin: 0 auto;
            position: relative;
            text-align: center;
        }

        .background-image {
            width: 100%;
            display: block;
        }

        /* Recuadro del nombre */
        .name-box {
            position: absolute;
            top: 200px;
            /* Ajusta según necesites */
            left: 50%;
            transform: translateX(-50%);
            width: 400px;
            min-height: 40px;
            padding: 18px 10px;
            border-radius: 40px;
            z-index: 10;
            background-color: #dbdddc;
            /* 🔴 Rojo */
            color: #000000;
            font-size: 32px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    <div class="card-container">

        {{-- Imagen generada --}}
        <img class="background-image" src="{{ $message->embed($url) }}"
            alt="Fondo Cumpleaños">
       

    </div>

</body>

</html>