<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'AuxoTracker') }} - Redirection</title>

        <!-- Redirection automatique vers la landing page -->
        @if(env('LANDING_PAGE_URL'))
        <meta http-equiv="refresh" content="0; url={{ env('LANDING_PAGE_URL') }}">
        <script type="text/javascript">
            // Redirection JavaScript en cas d'échec de la meta refresh
            window.location.href = "{{ env('LANDING_PAGE_URL') }}";
        </script>
        @endif

        <!-- Styles pour la page de chargement -->
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                color: white;
            }

            .container {
                text-align: center;
                padding: 2rem;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 15px;
                backdrop-filter: blur(10px);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .logo {
                font-size: 2.5rem;
                font-weight: bold;
                margin-bottom: 1rem;
                color: #fff;
            }

            .message {
                font-size: 1.2rem;
                margin-bottom: 2rem;
                opacity: 0.9;
            }

            .spinner {
                border: 4px solid rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                border-top: 4px solid #fff;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            .fallback-link {
                margin-top: 2rem;
                padding: 12px 24px;
                background: rgba(255, 255, 255, 0.2);
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 8px;
                color: white;
                text-decoration: none;
                display: inline-block;
                transition: all 0.3s ease;
            }

            .fallback-link:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="logo">AuxoTracker</div>
            @if(!env('LANDING_PAGE_URL'))
            <div class="message">Configuration de redirection manquante</div>
            <p style="opacity: 0.8; font-size: 1rem;">
                Veuillez configurer la variable LANDING_PAGE_URL dans votre fichier .env
            </p>
            @endif
        </div>

        <!-- Script de redirection avec délai de sécurité -->
        @if(env('LANDING_PAGE_URL'))
        <script>
            // Redirection avec délai de sécurité de 3 secondes
            setTimeout(function() {
                if (window.location.href.indexOf("{{ env('LANDING_PAGE_URL') }}") === -1) {
                    window.location.href = "{{ env('LANDING_PAGE_URL') }}";
                }
            }, 3000);
        </script>
        @endif
    </body>

</html>