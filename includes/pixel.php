<?php
/**
 * pixel.php
 *
 * Integrações:
 * - Google Analytics 4
 * - Google Ads
 * - Meta / Facebook Pixel
 *
 * Como usar:
 *
 * Coloque este arquivo em uma pasta do seu site e inclua dentro do <head>:
 *
 * <?php include __DIR__ . '/pixel.php'; ?>
 *
 * Depois altere apenas os IDs abaixo.
 */


/* =========================================================
   CONFIGURAÇÕES
========================================================= */

// Google Analytics 4
// Exemplo: G-ABC1234567
$GA4_ID = 'G-SEU_ID_AQUI';


// Google Ads
// Exemplo: AW-123456789
$GOOGLE_ADS_ID = 'AW-SEU_ID_AQUI';


// Meta / Facebook Pixel
// Exemplo: 123456789012345
$META_PIXEL_ID = '1359516986324535';


// Ativar ou desativar integrações
$ATIVAR_GA4 = true;
$ATIVAR_GOOGLE_ADS = true;
$ATIVAR_META_PIXEL = true;


/* =========================================================
   VALIDAÇÕES
========================================================= */

$ga4Ativo =
    $ATIVAR_GA4 &&
    !empty($GA4_ID) &&
    $GA4_ID !== 'G-SEU_ID_AQUI';


$googleAdsAtivo =
    $ATIVAR_GOOGLE_ADS &&
    !empty($GOOGLE_ADS_ID) &&
    $GOOGLE_ADS_ID !== 'AW-SEU_ID_AQUI';


$metaPixelAtivo =
    $ATIVAR_META_PIXEL &&
    !empty($META_PIXEL_ID) &&
    $META_PIXEL_ID !== '1359516986324535';


/*
|--------------------------------------------------------------------------
| ID principal utilizado para carregar a biblioteca Google Tag
|--------------------------------------------------------------------------
|
| Se o GA4 estiver configurado, usamos ele.
| Caso contrário, usamos o ID do Google Ads.
|
*/

$googleTagPrincipal = null;

if ($ga4Ativo) {

    $googleTagPrincipal = $GA4_ID;

} elseif ($googleAdsAtivo) {

    $googleTagPrincipal = $GOOGLE_ADS_ID;

}

?>


<?php if ($googleTagPrincipal): ?>

<!-- ======================================================
     GOOGLE TAG
     Google Analytics 4 + Google Ads
======================================================= -->

<script
    async
    src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(
        $googleTagPrincipal,
        ENT_QUOTES,
        'UTF-8'
    ) ?>">
</script>


<script>

    window.dataLayer = window.dataLayer || [];

    function gtag() {

        dataLayer.push(arguments);

    }


    gtag('js', new Date());


    <?php if ($ga4Ativo): ?>

    /*
    |--------------------------------------------------------------------------
    | GOOGLE ANALYTICS 4
    |--------------------------------------------------------------------------
    */

    gtag(
        'config',
        <?= json_encode($GA4_ID) ?>
    );

    <?php endif; ?>



    <?php if ($googleAdsAtivo): ?>

    /*
    |--------------------------------------------------------------------------
    | GOOGLE ADS
    |--------------------------------------------------------------------------
    */

    gtag(
        'config',
        <?= json_encode($GOOGLE_ADS_ID) ?>
    );

    <?php endif; ?>

</script>

<?php endif; ?>




<?php if ($metaPixelAtivo): ?>

<!-- ======================================================
     META / FACEBOOK PIXEL
======================================================= -->

<script>

    !function(f,b,e,v,n,t,s)
    {

        if(f.fbq)
            return;

        n = f.fbq = function()
        {

            n.callMethod
                ? n.callMethod.apply(n,arguments)
                : n.queue.push(arguments);

        };


        if(!f._fbq)
            f._fbq = n;


        n.push = n;

        n.loaded = true;

        n.version = '2.0';

        n.queue = [];


        t = b.createElement(e);

        t.async = true;

        t.src = v;


        s = b.getElementsByTagName(e)[0];

        s.parentNode.insertBefore(t,s);


    }(
        window,
        document,
        'script',
        'https://connect.facebook.net/en_US/fbevents.js'
    );


    /*
    |--------------------------------------------------------------------------
    | INICIALIZA META PIXEL
    |--------------------------------------------------------------------------
    */

    fbq(
        'init',
        <?= json_encode($META_PIXEL_ID) ?>
    );


    /*
    |--------------------------------------------------------------------------
    | REGISTRA VISUALIZAÇÃO DA PÁGINA
    |--------------------------------------------------------------------------
    */

    fbq(
        'track',
        'PageView'
    );

</script>


<!--
Fallback para usuários com JavaScript desativado
-->

<noscript>

    <img
        height="1"
        width="1"
        style="display:none"
        alt=""
        src="https://www.facebook.com/tr?id=<?= urlencode($META_PIXEL_ID) ?>&ev=PageView&noscript=1"
    />

</noscript>

<?php endif; ?>




<!-- ======================================================
     FUNÇÕES DE EVENTOS
======================================================= -->

<script>


/*
|--------------------------------------------------------------------------
| LEAD
|--------------------------------------------------------------------------
|
| Use quando a pessoa:
|
| - enviar formulário
| - solicitar orçamento
| - pedir contato
| - realizar cadastro
|
| Exemplo:
|
| registrarLead('formulario_contato');
|
*/

function registrarLead(origem = 'site')
{

    /* GOOGLE */

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'generate_lead',
            {

                lead_source: origem

            }
        );

    }


    /* META */

    if(typeof fbq === 'function')
    {

        fbq(
            'track',
            'Lead',
            {

                content_name: origem

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| CLIQUE WHATSAPP
|--------------------------------------------------------------------------
|
| Exemplo:
|
| registrarWhatsApp('botao_header');
|
*/

function registrarWhatsApp(origem = 'site')
{

    /* GOOGLE */

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'whatsapp_click',
            {

                event_category: 'contato',

                event_label: origem

            }
        );

    }


    /* META */

    if(typeof fbq === 'function')
    {

        fbq(
            'trackCustom',
            'WhatsAppClick',
            {

                origem: origem

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| CLIQUE TELEFONE
|--------------------------------------------------------------------------
*/

function registrarTelefone(origem = 'site')
{

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'phone_click',
            {

                event_category: 'contato',

                event_label: origem

            }
        );

    }


    if(typeof fbq === 'function')
    {

        fbq(
            'trackCustom',
            'PhoneClick',
            {

                origem: origem

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| VISUALIZAÇÃO DE PRODUTO OU SERVIÇO
|--------------------------------------------------------------------------
|
| Exemplo:
|
| registrarVisualizacao(
|     'Sistema para Clínica'
| );
|
*/

function registrarVisualizacao(nome = 'conteudo')
{

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'view_item',
            {

                items: [

                    {

                        item_name: nome

                    }

                ]

            }
        );

    }


    if(typeof fbq === 'function')
    {

        fbq(
            'track',
            'ViewContent',
            {

                content_name: nome

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| INÍCIO DE CHECKOUT
|--------------------------------------------------------------------------
*/

function registrarCheckout(
    valor = 0,
    moeda = 'BRL'
)
{

    valor = Number(valor) || 0;


    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'begin_checkout',
            {

                value: valor,

                currency: moeda

            }
        );

    }


    if(typeof fbq === 'function')
    {

        fbq(
            'track',
            'InitiateCheckout',
            {

                value: valor,

                currency: moeda

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| COMPRA
|--------------------------------------------------------------------------
|
| Exemplo:
|
| registrarCompra(
|     299.90,
|     'BRL',
|     'PEDIDO-123'
| );
|
*/

function registrarCompra(
    valor,
    moeda = 'BRL',
    pedido = ''
)
{

    valor = Number(valor) || 0;


    /*
    |--------------------------------------------------------------------------
    | GOOGLE ANALYTICS
    |--------------------------------------------------------------------------
    */

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            'purchase',
            {

                transaction_id: pedido,

                value: valor,

                currency: moeda

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | META PIXEL
    |--------------------------------------------------------------------------
    */

    if(typeof fbq === 'function')
    {

        fbq(
            'track',
            'Purchase',
            {

                value: valor,

                currency: moeda

            }
        );

    }

}




/*
|--------------------------------------------------------------------------
| CONVERSÃO GOOGLE ADS
|--------------------------------------------------------------------------
|
| O Conversion Label é fornecido pelo Google Ads.
|
| Exemplo:
|
| registrarConversaoGoogleAds(
|     'ABC123XYZ',
|     100,
|     'BRL'
| );
|
*/

function registrarConversaoGoogleAds(
    conversionLabel,
    valor = 1,
    moeda = 'BRL'
)
{

    <?php if ($googleAdsAtivo): ?>


    if(
        typeof gtag === 'function'
        &&
        conversionLabel
    )
    {

        gtag(
            'event',
            'conversion',
            {

                send_to:
                    <?= json_encode($GOOGLE_ADS_ID) ?>
                    +
                    '/'
                    +
                    conversionLabel,

                value:
                    Number(valor) || 0,

                currency:
                    moeda

            }
        );

    }


    <?php endif; ?>

}




/*
|--------------------------------------------------------------------------
| EVENTO PERSONALIZADO
|--------------------------------------------------------------------------
|
| Permite criar eventos genéricos.
|
| Exemplo:
|
| registrarEvento(
|     'download_catalogo'
| );
|
*/

function registrarEvento(
    evento,
    dados = {}
)
{

    if(!evento)
    {

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | GOOGLE
    |--------------------------------------------------------------------------
    */

    if(typeof gtag === 'function')
    {

        gtag(
            'event',
            evento,
            dados
        );

    }


    /*
    |--------------------------------------------------------------------------
    | META
    |--------------------------------------------------------------------------
    */

    if(typeof fbq === 'function')
    {

        fbq(
            'trackCustom',
            evento,
            dados
        );

    }

}

</script>