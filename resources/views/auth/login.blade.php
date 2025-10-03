<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>
    <style>
        /* Estilos simples para centrar el formulario */
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            height: 100vh;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 6px;
            box-shadow: 0 0 10px rgb(0 0 0 / 0.1);
            width: 320px;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.6rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 0.7rem;
            background-color: #3490dc;
            border: none;
            color: white;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2779bd;
        }
        .error {
            color: red;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar sesión</h2>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
            />

            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            />

            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
