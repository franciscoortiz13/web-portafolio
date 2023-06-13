<?php

require __DIR__ . '/vendor/autoload.php';


use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;


function getWoocommerceConfig()
{

    // Conexión WooComerce API destino
$woocommerce = new Client(
  'https://systemex.com.mx/',
  'ck_5b76b6243be873604e114745cd4b18bfe73e6551',
  'cs_8fa956bf0b5283b2a2a7353608e1d45ba2e89b02',
  [
    'wp_api' => true,
    'version' => 'wc/v3',
    'query_string_auth' => false,
  ]
);

    return $woocommerce;
}



function getJsonFromFile()
{
    $file = 'productos.json';
    $json = json_decode(file_get_contents($file), true);
    return $json;
}

function checkProductBySku($skuCode)
{
    $woocommerce = getWoocommerceConfig();

    $product = $woocommerce->get('products', [
        'sku' => $skuCode,
        'per_page' => 1,
    ]);

    return !empty($product) ? ['exist' => true, 'idProduct' => $product[0]['id']] : ['exist' => false, 'idProduct' => null];
}


function create_categories_from_json() {
    // Conexión WooComerce API destino
    $woocommerce = getWoocommerceConfig();
    // Lee y decodifica el archivo JSON en un array asociativo
    $data = getJsonFromFile();

    // Crea las categorías y subcategorías en Woocommerce
    foreach ($data as $product) {

        // Obtiene el nombre de la categoría y la subcategoría del producto
        $category_name = $product['categoria'];
        $subcategory_name = $product['subcategoria'];

        // Verifica si la categoría ya existe en Woocommerce
        $category = $woocommerce->get('products/categories', [
            'search' => $category_name,
            'per_page' => 1,
        ]);

        // Si no existe, crea la categoría
        if (empty($category)) {
            $category = $woocommerce->post('products/categories', [
                'name' => $category_name,
            ]);
        } else {
            $category = $category[0];
        }

        // Verifica si la subcategoría ya existe en Woocommerce
        $subcategory = $woocommerce->get('products/categories', [
            'search' => $subcategory_name,
            'per_page' => 1,
        ]);

        // Si no existe, crea la subcategoría como una subcategoría de la categoría
        if (empty($subcategory)) {
            $subcategory = $woocommerce->post('products/categories', [
                'name' => $subcategory_name,
                'parent' => $category->id,
            ]);
        } else {
            $subcategory = $subcategory[0];
        }
    }
}



function createProducts()
{
    $woocommerce = getWoocommerceConfig();
    $products = getJsonFromFile();

    // Divide el arreglo de productos en grupos de 100
    $productGroups = array_chunk($products, 100);

    foreach ($productGroups as $productGroup) {

        foreach ($productGroup as $product) {

            $productExist = checkProductBySku($product['clave']);

            if ($productExist['exist']) {
                // Actualizar información del producto
                $idProduct = $productExist['idProduct'];
                $woocommerce->put('products/' . $idProduct, [
                    'name' => $product['nombre'],
                    'slug' => strtolower(str_replace(' ', '-', $product['nombre'])),
                    'description' => $product['descripcion_corta'],
                    //'regular_price' => $product['precio'],
                    'categories' => [
                        ['id' => getCategoryId($product['categoria'])],
                        ['id' => getCategoryId($product['subcategoria'])]
                    ],
                    'images' => [
                        [
                            'src' => $product['imagen'],
                            'position' => 0
                        ]
                    ],
                    'attributes' => getAttributes($product['especificaciones'])
                ]);
            } else {
                // Crear nuevo producto
                $woocommerce->post('products', [
                    'name' => $product['nombre'],
                    'type' => 'simple',
                    'sku' => $product['clave'],
                    'description' => $product['descripcion_corta'],
                    //'regular_price' => $product['precio'],
                    'categories' => [
                        ['id' => getCategoryId($product['categoria'])],
                        ['id' => getCategoryId($product['subcategoria'])]
                    ],
                    'images' => [
                        [
                            'src' => $product['imagen'],
                            'position' => 0
                        ]
                    ],
                    'attributes' => getAttributes($product['especificaciones'])
                ]);
            }
        }
    }
}

function getCategoryId($name) {
    $woocommerce = getWoocommerceConfig();

    $category = $woocommerce->get('products/categories', [
        'search' => $name,
        'per_page' => 1,
    ]);

    if (!empty($category)) {
        return $category[0]->id;
    } else {
        $category = $woocommerce->post('products/categories', [
            'name' => $name,
        ]);
        return $category->id;
    }
}


function getAttributes($especificaciones) {
    $attributes = [];
    foreach ($especificaciones as $spec) {
        $attributes[] = [
            'name' => $spec['tipo'],
            'options' => [$spec['valor']],
            'visible' => true,
            'variation' => false
        ];
    }
    return $attributes;
}



function getproductAtributesNames($articulos)
{
    $keys = array();
    foreach ($articulos as $articulo) {
        $terms = $articulo['config'];
        foreach ($terms as $key => $term) {
            array_push($keys, $key);
        }
    }
       /* remove repeted keys*/
    $keys = array_unique($keys);
    $configlist = array_column($articulos, 'config');
    $options = array();
    foreach ($keys as $key) {
        $attributes = array(
            array(
                'name' => $key,
                'slug' => 'attr_' . $key,
                'visible' => true,
                'variation' => true,
                'options' => getTermsByKeyName($key, $configlist)
            )
        );
    }
    return $attributes;
}

function getTermsByKeyName($keyName, $configList)
{
    //var_dump($configList);
    $options = array();
    foreach ($configList as $config) {
        foreach ($config as $key => $term) {
            if ($key == $keyName) {
                array_push($options, $term);
            }
        }
    }
    return $options;
}



function prepareInitialConfig()
{
    echo ('Importing data, wait...')."\n";
    //create_categories_from_json();
    createProducts();
    echo ('Done!')."\n";
}

prepareInitialConfig();



?>