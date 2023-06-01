/**
 * Gestión de Prepedidos y vendidos sin stock
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

 //generamos aquí para que esté disponible en las diferentes funciones un array para ir almacenando nombre, referencia presta y proveedor  y ean de los productos que mostremos para buscar en dicho array con el buscador integrado.
 //será el key un array id_producto+id_atributo que contiene esos 4 valores 
 let array_productos = [];

 //generamos el array de los proveedores que tienen los productos de la lista que se muestre, para luego poder filtrar por proveedor
 let array_proveedores = [];

 //array para los filtros, con cada producto se guardará nombre proveedor, si permite  o no pedidos, si tiene o no stock
 let array_filtros_producto = [];

 //array de filtros aplicados, se guardan los filtros que tienen que estar aplicados hasta que se recarguen los productos o se eliminen.
 //filtros: si_permite, no_permite, sin_stock, con_stock, proveedor (si viene la palabra proveedor, buscaremos el valor del select para sacar su nombre)
 let array_filtros_aplicados = [];

 //para mostrar el botón de scroll arriba, aparecerá cuando se haga scroll abajo y desaparecerá al volver arriba
 $(window).scroll(function(){    
    if ($(this).scrollTop() > 400) {
      $('#boton_scroll').fadeIn();
    } else {
      $('#boton_scroll').fadeOut();
    }
  });

//$(document).ready(function(){
document.addEventListener('DOMContentLoaded', start);

function start() {

    //quitamos cosas del panel header que pone Prestashop por defecto, para que haya más espacio. 
    document.querySelector('h2.page-title').remove(); 
    document.querySelector('div.page-bar.toolbarBox').remove(); 
    document.querySelector('div.page-head').style.height = '36px';    

    //el panel que contiene el formulario, etc donde aparecerá el contenido lo hacemos relative y colocamos para que aparezca inicialmente bajo la botonera poniéndole top 60px, top respecto a qué no lo sé.
    const panel_contenidos = document.querySelector('div#content div.row'); 
    panel_contenidos.style.position = 'relative';
    panel_contenidos.style.top = '60px';
    
    // obtenemos token del input hidden que hemos creado con id 'token_admin_modulo_'.$token_admin_modulo, para ello primero buscamos el id de un input cuyo id comienza por token_admin_modulo y al resultado le hacemos substring.     
    // id_hiddeninput = $("input[id^='token_admin_modulo']").attr('id');
    const id_hiddeninput = document.querySelector("input[id^='token_admin_modulo']").id;
    //console.log('token input '+id_hiddeninput);

    //substring, desde 19 hasta final(si no se pone lenght coge el resto de la cadena)
    const token = id_hiddeninput.substr(19);

    //sacamos panel-heading para poner el tipo de productos que mostramos según el botón pulsado
    const panelHeading = document.querySelector('.panel-heading');

    //Añadimos un panel arriba, sobre el .panel-heading, (al final se coloca bajo page-head) donde pondremos los botones para escoger Prepedidos o Vendidos sin stock y en espera.
    //creamos div,añadimos las calses y usamos html para innerhtml
    const panel_botones = document.createElement('div');
    panel_botones.classList.add('row','text-center');
    panel_botones.id = "button_panel";
    panel_botones.innerHTML =  `<div class="col-lg-6">
                                    <div class="panel">
                                        <div class="clearfix">
                                            <h3>Selecciona productos a mostrar</h3>
                                            <div class="row container_botones" id="div_botones_mostrar">
                                                <div class="col-lg-6 botones_acciones">
                                                    <button type="button" id="mostrar_prepedidos" class="btn btn-default">
                                                        PREPEDIDOS
                                                    </button>
                                                </div>
                                                <div class="col-lg-6 botones_acciones">
                                                    <button type="button" id="mostrar_en_espera" class="btn btn-default">
                                                        VENDIDOS EN ESPERA
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
              

    //generamos el botón para subir hasta arriba haciendo scroll
    const boton_scroll = document.createElement('div');    
    boton_scroll.id = "boton_scroll";
    boton_scroll.innerHTML =  `<i class="icon-arrow-up"></i>`;

    boton_scroll.addEventListener('click', scrollArriba);

    //lo append a la botonera, y con css lo haremos fixed
    panel_botones.appendChild(boton_scroll);
 

    //sacamos del documento el elemento de id ajaxBox, colocando los botones junto a él , justo después
    // const ajaxBox = document.querySelector('#ajaxBox');
    // ajaxBox.insertAdjacentElement('afterend', panel_botones);

    document.querySelector('div.page-head').insertAdjacentElement('afterend', panel_botones);
    //si el Prestashop del usuario tiene el menú a la izquierda, ponemos una clase a la botonera que le otorgará un fixed en una posición, y si lo tiene superior ponemos otra clase para otra posición
    if (document.contains(document.querySelector('nav#nav-sidebar'))) {
        document.querySelector('#button_panel').classList.add('panel_lateral');
    } else if (document.contains(document.querySelector('nav#nav-topbar'))) {
        document.querySelector('#button_panel').classList.add('panel_superior');
        // document.querySelector('#main div.bootstrap').appendChild(panel_botones);
    } 

    // document.querySelector('#content').appendChild(panel_contenidos);  

    //TAMBIEN VALDRÍA : sacamos el div de clase bootstrap antes del cual quiero meter los botones
    // const claseBootstrap = document.querySelectorAll(".bootstrap")[4];
    // claseBootstrap.insertBefore(panel_botones, claseBootstrap[0]);      

    //ponemos event listener para ambos botones, y según se pulse uno u otro llamaremos a una función ajax para obtener la lista de productos. Después pondremos event listeners para los diferentes botones y opciones por cada producto en la lista generada.
    const mostrarPrepedidos = document.querySelector('#mostrar_prepedidos');
    const mostrarEspera = document.querySelector('#mostrar_en_espera');

    mostrarPrepedidos.addEventListener('click', mostrarProductosPrepedido);
    mostrarEspera.addEventListener('click', mostrarProductosEspera);

    function mostrarProductosPrepedido() {
        //console.log('productos prepedido');

        //cambiamos el texto del panel-heading        
        panelHeading.innerHTML = 'Productos con categoría prepedido';

        //mostramos spinner
        Spinner();

        //si existen ponemos los botones y el select de filtro en su posición neutra y limpiamos array de filtros
        array_filtros_aplicados = [];
        if (document.contains(document.querySelector('#todos_permitir'))) {
            document.querySelector('#todos_permitir').checked = true;
        }
        if (document.contains(document.querySelector('#todos_stock'))) {
            document.querySelector('#todos_stock').checked = true;
        }
        if (document.contains(document.querySelector('#proveedores'))) {
            document.querySelector('#proveedores').value = 'todos';
        } 
        
        //vamos a hacer una petición ajax a la función ajaxListaPrepedidos en el controlador AdminProductosPrepedido que nos devuelva la lista de productos con categoría prepedido 
        var dataObj = {};
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=lista_prepedidos" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax en data.info_productos la información de los productos
                    //console.log('data.info_productos = '+data.info_productos);
                    //console.dir(data.info_productos);                    

                    //con los datos, llamamos a la función que nos los mostrará
                    muestraListaProductos(data.info_productos); 

                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                }
                else
                {                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }

    function mostrarProductosEspera() {
        //console.log('productos en espera');

        //cambiamos el texto del panel-heading        
        panelHeading.innerHTML = 'Productos vendidos sin stock en pedidos en espera';

        //mostramos spinner
        Spinner();

        //si existen ponemos los botones y el select de filtro en su posición neutra y limpiamos array de filtros
        array_filtros_aplicados = [];
        if (document.contains(document.querySelector('#todos_permitir'))) {
            document.querySelector('#todos_permitir').checked = true;
        }
        if (document.contains(document.querySelector('#todos_stock'))) {
            document.querySelector('#todos_stock').checked = true;
        }
        if (document.contains(document.querySelector('#proveedores'))) {
            document.querySelector('#proveedores').value = 'todos';
        } 
        
        //vamos a hacer una petición ajax a la función ajaxListaEnEspera en el controlador AdminProductosPrepedido que nos devuelva la lista de productos vendidos sin stock en pedidos en espera
        var dataObj = {};
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=lista_en_espera" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax en data.info_productos la información de los productos
                    //console.log('data.info_productos = '+data.info_productos);
                    // console.dir(data.info_productos);

                    //con los datos, llamamos a la función que nos los mostrará
                    muestraListaProductos(data.info_productos);     
                    
                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                }
                else
                {        
                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }        

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }


    function muestraListaProductos(productos) {

        //comprobamos si el panel de productos ya existe (se ha pulsado un botón antes) y si existe lo eliminamos para que no se añadan delante de nuevo
        if (document.contains(document.querySelector('#panel_productos'))) {
            document.querySelector('#panel_productos').remove();
        } 
        //comprobamos también si hay un panel de pedidos (si venimos de ver los pedidos) y lo eliminamos si es así
        if (document.contains(document.querySelector('#panel_pedidos'))) {
            document.querySelector('#panel_pedidos').remove();
        } 

        //comprobamos la existencia del panel de acciones de pedidos y lo eliminamos, si venimos de una vista de pedidos
        if (document.contains(document.querySelector('#accion_pedidos_panel'))) {
            document.querySelector('#accion_pedidos_panel').remove();
        }

        //comprobamos la existencia del  buscador, para que no se añada varias veces
        if (!document.contains(document.querySelector('#buscador_panel'))) {
            //creamos el panel con el buscador para añadirlo cuando se pulse a buscar una lista
            const panel_buscador = document.createElement('div');
            panel_buscador.classList.add('col-lg-6');
            panel_buscador.id = "buscador_panel";
            panel_buscador.innerHTML = `<div class="panel">
                                            <div class="clearfix">
                                                <h3>Buscador</h3>
                                                <div class="row container_botones">
                                                    <div class="col-lg-4 botones_acciones">
                                                        <div class="col-lg-6">
                                                            <label for="switch_permitir_pedidos">Permitir Pedidos</label>
                                                            <div id="switch_permitir_pedidos" class="switch-toggle-permitir switch-3 switch-candy">

                                                                <input id="solo_permitir" name="permitirpedidos" type="radio" checked="" />
                                                                <label for="solo_permitir">SI</label>
                                                            
                                                                <input id="todos_permitir" name="permitirpedidos" type="radio" checked="checked" />
                                                                <label for="todos_permitir">TODOS</label>
                                                            
                                                                <input id="solo_no_permitir" name="permitirpedidos" type="radio" />
                                                                <label for="solo_no_permitir">NO</label>
                                                            
                                                            </div>
                                                            
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <label for="switch_stock_fisico">Stock Físico</label>
                                                            <div id="switch_permitir_pedidos" class="switch-toggle-stock switch-3 switch-candy">

                                                                <input id="solo_con_stock" name="stockfisico" type="radio" checked="" />
                                                                <label for="solo_con_stock">SI</label>
                                                            
                                                                <input id="todos_stock" name="stockfisico" type="radio" checked="checked" />
                                                                <label for="todos_stock">TODOS</label>
                                                            
                                                                <input id="solo_sin_stock" name="stockfisico" type="radio" />
                                                                <label for="solo_sin_stock">NO</label>
                                                            
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-4 botones_acciones">
                                                        <input id="buscador" type="text"  placeholder="Nombre, referencia de Prestashop o Proveedor, Ean">
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <div class="col-lg-2">
                                                        </div>
                                                        <div class="col-lg-8">
                                                            <label class="control-label">Proveedor</label>
                                                            <select id="proveedores" class="fixed-width-md">                                                                
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-2">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>`;
            
            document.querySelector('#button_panel').appendChild(panel_buscador);
        

            //ponemos un eventlistener para el input del buscador
            const buscador = document.querySelector('#buscador');
            buscador.addEventListener('input', buscaProducto);

            //event listener para botones de permite pedido y tiene stock y el select de proveedor. Si se cambia alguno de ellos, vaciamos el input del buscador. Se podrá usar para los productos filtrados, pero no filtrar sobre el resultado de la búsqueda
            document.querySelector('#solo_permitir').addEventListener('click', function(){
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está si_permite no hacemos nada, si está no_permite lo eliminamos y metemos si_permite
                if (array_filtros_aplicados.includes('no_permite')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('no_permite'), 1); //eliminamos no_permite
                    array_filtros_aplicados.push('si_permite');
                } else {
                    array_filtros_aplicados.push('si_permite');
                }
                filtraProductos();
            });

            document.querySelector('#solo_no_permitir').addEventListener('click', function(){   
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está no_permite no hacemos nada, si está si_permite lo eliminamos y metemos no_permite
                if (array_filtros_aplicados.includes('si_permite')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('si_permite'), 1); //eliminamos si_permite
                    array_filtros_aplicados.push('no_permite');
                } else {
                    array_filtros_aplicados.push('no_permite');
                }             
                filtraProductos();
            });

            document.querySelector('#todos_permitir').addEventListener('click', function(){   
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está no_permite o si_permite los eliminamos
                if (array_filtros_aplicados.includes('si_permite')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('si_permite'), 1); //eliminamos si_permite                    
                } 
                if (array_filtros_aplicados.includes('no_permite')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('no_permite'), 1); //eliminamos no_permite                    
                }             
                filtraProductos();
            });

            document.querySelector('#solo_sin_stock').addEventListener('click', function(){  
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está sin_stock no hacemos nada, si está con_stock lo eliminamos y metemos sin_stock
                if (array_filtros_aplicados.includes('con_stock')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('con_stock'), 1); //eliminamos con_stock
                    array_filtros_aplicados.push('sin_stock');
                } else {
                    array_filtros_aplicados.push('sin_stock');
                }                 
                filtraProductos();
            });

            document.querySelector('#solo_con_stock').addEventListener('click', function(){  
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está con_stock no hacemos nada, si está sin_stock lo eliminamos y metemos con_stock
                if (array_filtros_aplicados.includes('sin_stock')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('sin_stock'), 1); //eliminamos sin_stock
                    array_filtros_aplicados.push('con_stock');
                } else {
                    array_filtros_aplicados.push('con_stock');
                }                   
                filtraProductos();
            });

            document.querySelector('#todos_stock').addEventListener('click', function(){   
                buscador.value = '';
                //comprobamos el array de filtros aplicados, si está sin_stock o con_stock los eliminamos
                if (array_filtros_aplicados.includes('sin_stock')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('sin_stock'), 1); //eliminamos sin_stock                    
                } 
                if (array_filtros_aplicados.includes('con_stock')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('con_stock'), 1); //eliminamos con_stock                   
                }             
                filtraProductos();
            });

        } 

        //vaciamos input de buscador
        buscador.value = '';       

        //habilitamos buscador
        //document.querySelector('#buscador').disabled = false;

        //vamos generando la lista/formulario dinamicamente utilizando las clases (panel, label etc) de prestashop
        //creamos el div que contiene el formulario, lo añadiremos después del panel heading
        const form_wrapper = document.createElement('div');
        form_wrapper.classList.add('form-wrapper');
        form_wrapper.id = 'panel_productos';        

        //var form_wrapper = '<div class="form-wrapper" id="panel_productos"></div>';
        
        document.querySelector('.panel-heading').insertAdjacentElement('afterend', form_wrapper);

        //$('.panel-heading').after(form_wrapper);

        //contador para los productos
        var num_producto = 0;

        //vaciamos el array_productos para asegurarnos de que no se acumulen productos si se muestra una lista y luego otra
        array_productos = [];

        //vaciamos el array de filtros producto
        array_filtros_producto = [];

        //vaciamos el array de proveedores
        array_proveedores = [];

        //var productos = data.info_productos;
        productos.forEach(
            producto => {
                // console.log(producto.id_product);
                // console.log(producto.nombre);

                var el_producto = producto.el_producto;
                
                //rellenamos el array para el buscador.                
                array_productos[el_producto] = [producto.nombre, producto.referencia, producto.ref_proveedor, producto.ean13];

                // rellenamos el array filtros con si permite o no pedidos, si tiene o no stock físico, y el nombre de proveedor
                var stock_fisico = producto.stock_online + producto.stock_tienda;
                var permite_pedido = (producto.permite_ahora == 1) ? 'si' : 'no';  //permite_ahora es out_of_stock, solo permite si vale 1, puede valer 0 o 2 también
                var tiene_stock = (stock_fisico > 0) ? 'si' : 'no';

                array_filtros_producto[el_producto] = [producto.proveedor, permite_pedido, tiene_stock];

                //metemos el nombre del proveedor de cada producto al array, evitando repeticiones, para que en el selct del filtro solo estén los que están en la lista de productos
                if (!array_proveedores.includes(producto.proveedor)) {
                    array_proveedores.push(producto.proveedor);
                }    

                num_producto++;

                if (producto.permite_ahora == 1) {
                    var permite = '<span class="alerta-naranja" id="span_permitir_'+el_producto+'" style="font-size: 200%;">SI PERMITE PEDIDOS</span>';
                    //preparar botón quitar permitir pedido
                    var boton_permitir = '\
                    <button type="button" id="eliminar_permitir_'+el_producto+'" class="btn btn-sm btn-basic  toggle_permitir" title="QUITAR permitir pedidos sin stock al producto">\
                        <i class="icon-minus"></i> Quitar Permitir\
                    </button>';
                } else {
                    var permite = '<span style="font-size: 200%;" id="span_permitir_'+el_producto+'">NO PERMITE PEDIDOS</span>';
                    //preparar botón poner permitir pedido
                    var boton_permitir = '\
                    <button type="button" id="poner_permitir_'+el_producto+'" class="btn btn-sm btn-default toggle_permitir" title="PONER permitir pedidos sin stock al producto">\
                        <i class="icon-plus"></i> Poner Permitir\
                    </button>';
                }

                if (producto.cat_prepedido_ahora == 'Si') {
                    var prepedido_ahora = '<span class="alerta-naranja" id="span_prepedido_'+el_producto+'" style="font-size: 200%;">PREPEDIDO</span>';
                    //preparar botón quitar prepedido
                    var boton_prepedido = '\
                    <button type="button" id="eliminar_prepedido_'+el_producto+'" class="btn btn-sm btn-basic toggle_prepedido" title="QUITAR categoría PREPEDIDO al producto">\
                        <i class="icon-minus"></i> Quitar Prepedido\
                    </button>';
                } else {
                    var prepedido_ahora = '<span style="font-size: 200%;" id="span_prepedido_'+el_producto+'">NO PREPEDIDO</span>';
                    //preparar botón poner prepedido
                    var boton_prepedido = '\
                    <button type="button" id="poner_prepedido_'+el_producto+'" class="btn btn-sm btn-default toggle_prepedido" title="PONER categoría PREPEDIDO al producto">\
                        <i class="icon-plus"></i> Poner Prepedido\
                    </button>';
                }   
                
                if (producto.con_atributos == 1) {
                    if (producto.disponibilidad == 'Fechas diferentes') {
                        disponibilidad = '<span class="alerta-naranja mensaje-atributos" style="font-size: 100%;">Producto con Atributos</span><br><br>\
                        <span class="alerta-roja" style="font-size: 150%;" id="span_fecha_'+el_producto+'">Fechas diferentes</span>';
                    } else if (producto.disponibilidad == '0000-00-00') {
                        disponibilidad = '<span class="alerta-naranja mensaje-atributos" style="font-size: 100%;">Producto con Atributos</span><br><br>\
                        <span class="alerta-roja" style="font-size: 150%;" id="span_fecha_'+el_producto+'">Fecha No establecida</span>';
                    } else {
                        //formamos la fecha formato dd-mm-yyy
                        var fecha_antes = producto.disponibilidad.split('-');
                        var fecha = [fecha_antes[2],fecha_antes[1],fecha_antes[0]].join('-');
                        disponibilidad = '<span class="alerta-naranja mensaje-atributos" style="font-size: 100%;">Producto con Atributos</span><br><br>\
                        <span class="alerta-verde" style="font-size: 150%;" id="span_fecha_'+el_producto+'">'+fecha+'</span>';
                    }
                } else {
                    if (producto.disponibilidad == '0000-00-00') {
                        disponibilidad = '<span class="alerta-roja mensaje-atributos" style="font-size: 150%;" id="span_fecha_'+el_producto+'">Fecha No establecida</span>';
                    } else {
                        //formamos la fecha formato dd-mm-yyy
                        var fecha_antes = producto.disponibilidad.split('-');
                        var fecha = [fecha_antes[2],fecha_antes[1],fecha_antes[0]].join('-');
                        disponibilidad = '<span class="alerta-verde mensaje-atributos" style="font-size: 200%;" id="span_fecha_'+el_producto+'">'+fecha+'</span>';
                    }
                }          

                const casilla = document.createElement('div');
                casilla.classList.add('form-group','div_producto');
                casilla.id = 'form_group_'+el_producto; 
                casilla.innerHTML = "<br>\
                        <div class='col-lg-12' id='info_product_"+el_producto+"'>\
                        </div>";   
                
                document.querySelector('#panel_productos').appendChild(casilla);

                //si producto.ref_proveedor = 1 es un producto con varios atributos, por lo tanto si se muestra la lista de productos Prepedido mostramos mensaje, ya que en esa lista ignoramos atributos
                if (producto.ref_proveedor == 1) {
                    var referencia_proveedor = 'Tiene Atributos';
                } else {
                    var referencia_proveedor = producto.ref_proveedor;
                }

                // var casilla = "\
                // <div class='form-group div_producto' id='form_group_"+el_producto+"'>\
                //     <br>\
                //     <div class='col-lg-12' id='info_product_"+el_producto+"'>\
                //     </div>\
                // </div>";

                //lo añadimos al wrapper, con append va al final, dentro.
                // $('#panel_productos').append(casilla);

                //<img src="'+producto.url_imagen+'"  width="170" height="227"/>

                var informacion_producto = '<div class="panel clearfix panel_producto"><h3><span  title="'+el_producto+'" id="nombre_'+el_producto+'"><a href="'+producto.url_producto+'" target="_blank" style="text-decoration: none;">'+producto.nombre+'</a></span></h3>\
                    <div class="col-lg-2 contenedor_imagen contenido_producto">\
                        <img src="'+producto.url_imagen+'"  width="200" height="267"/>\
                    </div>\
                    <div class="col-lg-2 contenido_producto">\
                        <br>\
                        <h3><span id="ref_presta_'+el_producto+'">'+producto.referencia+'</span></h3>\
                        <p>Ean13:  <span id="ean_'+el_producto+'"><b>'+producto.ean13+'</b></span></p>\
                        <p>Categoría:  <b>'+producto.categoria+'</b></p>\
                        <p>Proveedor:  <b>'+producto.proveedor+'</b></p>\
                        <p>Referencia Proveedor:  <br><br><span style="padding-left:30px;" id="ref_prov_'+el_producto+'"><b>'+referencia_proveedor+'</b></span></p>\
                    </div>\
                    <div class="col-lg-3 contenido_producto">\
                        <div class="col-lg-12 text-center contenido_producto">\
                            <br>\
                            <p>'+prepedido_ahora+'</p>\
                            <p>'+permite+'</p>\
                            <p><b>Stock Disponible</b></p>\
                            <p><span style="font-size: 200%;">'+producto.online_mas_tienda+'</span></p>\
                        </div>\
                        <div class="col-lg-12 contenido_producto">\
                            <br>\
                            <div class="col-lg-6 text-center contenido_producto">\
                                <p><b>Online</b></p>\
                                <p><span style="font-size: 200%;">'+producto.stock_online+'</span></p>\
                            </div>\
                            <div class="col-lg-6 text-center contenido_producto">\
                                <p><b>Tienda</b></p>\
                                <p><span style="font-size: 200%;">'+producto.stock_tienda+'</span></p>\
                            </div>\
                        </div>\
                    </div>\
                    <div class="col-lg-2 text-center contenido_producto">\
                        <br>\
                        <p><b>Unidades en espera</b></p>\
                        <p><span style="font-size: 200%;">'+producto.unidades_espera+'</span></p>\
                        <p><b>Ventas Totales</b></p>\
                        <p><span style="font-size: 200%;">'+producto.venta_total+'</span></p>\
                        <p><b>Disponibilidad</b></p>\
                        <p>'+disponibilidad+'</p>\
                    </div>\
                    <div class="col-lg-3 text-center contenido_producto" style="align-items: center;">\
                        <br>\
                        <div class="row contenido_producto" id="div_fecha_disponibilidad_'+el_producto+'">\
                            <label class="control-label">\
                                <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="SI EL PRODUCTO TIENE ATRIBUTOS SE ASIGNARÁ LA MISMA FECHA A TODOS ELLOS" data-original-title="Introduce la nueva fecha de disponibilidad">\
                                    Nueva Fecha <br>de disponibilidad\
                                </span>\
                            </label>\
                            <input class="fecha_disponibilidad" id="disponibilidad_'+el_producto+'" type="date" name="disponibilidad_'+el_producto+'"  placeholder="dd-mm-yyyy" value="" min="1997-01-01" max="2030-12-31">\
                            <button type="button" id="guarda_fecha_disponibilidad_'+el_producto+'" name="guarda_fecha_disponibilidad_'+el_producto+'" class="btn btn-sm btn-default guarda_fecha_disponibilidad">\
                                <i class="icon-save"></i> Guardar\
                            </button>\
                        </div>\
                        <br>\
                        <div class="row contenido_producto" id="div_mensaje_available_'+el_producto+'">\
                            <div class="col-lg-9 contenido_producto">\
                                <textarea id="text_available_later_'+el_producto+'" rows="2">'+producto.mensaje+'</textarea>\
                            </div>\
                            <div class="col-lg-3 contenido_producto">\
                                <button type="button" id="guarda_available_later_'+el_producto+'" name="guarda_available_later_'+el_producto+'" class="btn btn-sm btn-default pull-right guarda_available_later" title="Solo guardará el mensaje en castellano">\
                                    <i class="icon-save"></i> Guardar\
                                </button>\
                            </div>\
                        </div>\
                        <br>\
                        <div class="row contenido_producto" id="div_botones_'+el_producto+'">\
                            <div class="col-lg-6 contenido_producto" id="div_boton_prepedido_'+el_producto+'">\
                                '+boton_prepedido+'\
                            </div>\
                            <div class="col-lg-6 contenido_producto" id="div_boton_permitir_'+el_producto+'">\
                                '+boton_permitir+'\
                            </div>\
                        </div>\
                        <br>\
                        <div class="row contenido_producto" id="div_boton_revisar_'+el_producto+'">\
                            <div class="col text-center">\
                                <button type="button" id="revisar_producto_'+el_producto+'" name="revisar_producto_'+el_producto+'" class="btn btn-sm btn-primary revisar_producto" title="Revisar pedidos asociados al producto">\
                                    <i class="icon-database"></i> Revisar Producto y Pedidos\
                                </button>\
                            </div>\
                        </div>\
                    </div>\
                </div>';   
                                
                document.querySelector('#info_product_'+el_producto).innerHTML = informacion_producto;

                //$('#info_product_'+el_producto).append(informacion_producto);

            }

        );

        // console.log(array_productos);        
        // console.log(array_productos['2024_0'][3]);

        //rellenamos el select de proveedores del panel de botones, junto al buscador. Se hará en función de los proveedores que haya en "productos", que es la lista de productos que se van a mostrar con los nombres almacenados en el array array_proveedores
        const select_proveedores = document.querySelector('#proveedores');
        //vaciamos el selet
        select_proveedores.innerHTML = '';   

        var options_select = '<option value="todos" selected>Todos</option>'; //permitimos un proveedor nulo "todos", que si es seleccionado limpie el filtro de proveedor

        //ordenamos array alfabeticamente
        array_proveedores.sort();

        array_proveedores.forEach( value=> {
            options_select += '<option value="'+value+'">'+value+'</option>';
        });

        // console.log(options_select);
        select_proveedores.innerHTML = options_select;             
        select_proveedores.addEventListener('change', function(){
            buscador.value = ''; //vaciamos input del buscador
            // console.log(this.value);
            //si se selecciona un proveedor, metemos "proveedor" en el array de filtros y en la función de filtrar buscaremos el value del select. Si se selecciona Todos, eliminamos la palabra "proveedor" del array si está   
            if (this.value == 'todos') {
                if (array_filtros_aplicados.includes('proveedor')) {
                    array_filtros_aplicados.splice(array_filtros_aplicados.indexOf('proveedor'), 1); //eliminamos proveedor                   
                }
            } else {
                if (!array_filtros_aplicados.includes('proveedor')) {
                    //si se ha marcado un proveedor y no aparece proveedor en el array lo metemos, si no no, para no repetir
                    array_filtros_aplicados.push('proveedor');                  
                }
                
            }           
               
            filtraProductos();
        });
        
        //añadimos al texto de panel-heading el número de productos mostrados           
        // panelHeading.innerHTML = panelHeading.innerHTML+' - '+num_producto+' Productos';
        panelHeading.innerHTML += ' - '+num_producto+' Productos';

        //recogemos los botones generados por su clase para añadirles un event listener. Como se genera una lista de nodos (una clase varios botones) hay que hacer un foreach para asignarle el event listener a cada boton

        //Guardar fecha de disponibilidad
        const botones_guarda_fecha = document.querySelectorAll('.guarda_fecha_disponibilidad');
        console.log(botones_guarda_fecha.length);

        botones_guarda_fecha.forEach( item => {
            item.addEventListener('click', fechaDisponibilidad);
        });

        //guardar mensaje de available later
        const botones_guarda_mensaje = document.querySelectorAll('.guarda_available_later');

        botones_guarda_mensaje.forEach( item => {
            item.addEventListener('click', mensajeDisponibilidad); 
        });

        //Poner/quitar categoría prepedido
        const botones_prepedido = document.querySelectorAll('.toggle_prepedido');

        botones_prepedido.forEach( item => {
            item.addEventListener('click', categoriaPrepedido);
        });

        //Poner/quitar Permitir pedido
        const botones_permitir_pedido = document.querySelectorAll('.toggle_permitir');

        botones_permitir_pedido.forEach( item => {
            item.addEventListener('click', permitirPedidos); 
        });

        //Abrir info y pedidos de producto seleccionado
        const botones_revisar_producto = document.querySelectorAll('.revisar_producto');

        botones_revisar_producto.forEach( item => {
            item.addEventListener('click', buscaPedidos);             
        });

    }

};

//función para subir cuando se pulsa el botón de scroll arriba
function scrollArriba() {
    $('html, body').animate({scrollTop : 0},1000);
    // return false;
}

function fechaDisponibilidad(e) {
    //console.log(e.target);
    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('guarda_fecha_disponibilidad')){
        // console.log('pulsado guarda fecha');
        // console.log(e.currentTarget.id);
        //para sacar el id_product y attribute, cogemos el id del botón, hacemos split y cogemos como id_attribute el último trozo, e id_product el penúltimo
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_producto = splitBotonId[splitBotonId.length - 2];
        var id_producto_atributo = splitBotonId[splitBotonId.length - 1];
        console.log(id_producto);
        console.log(id_producto_atributo);

        var nueva_fecha = document.querySelector('#disponibilidad_'+id_producto+'_'+id_producto_atributo).value;
        console.log(nueva_fecha);

        //comprobamos que hubiera fecha seleccionada y que la fecha sea posterior a la fecha actual. Generando objetos se pueden compara con < y > (no con == etc)
        // 25/03/2021 Vamos a permitir que se elimine la fecha, es decir, se ponga a 0000-00-00
        if (!nueva_fecha) {
            nueva_fecha = '0000-00-00';
        }
        console.log(nueva_fecha);

        const hoy = new Date();
        const nueva = new Date(nueva_fecha);
        if (((nueva - hoy) < 0)) {
            //la fecha es anterior, no continuamos
            showErrorMessage('La fecha seleccionada ya ha pasado');
            return;
        }

        //mostramos spinner
        Spinner();

        //vamos a hacer una petición ajax a la función ajaxFechaDisponibilidad en el controlador AdminProductosPrepedido que cambie el campo available_date del producto. Si tiene atributos se le pondrá la misma fecha a todos
        var dataObj = {};
        dataObj['id_product'] = id_producto;
        dataObj['fecha'] = nueva_fecha;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=fecha_disponibilidad" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax la confirmación o no de haber cambiado la categoría prepedido
                    console.dir(data.resultado);
                    
                    //modificamos el campo disponibilidad que muestra la fecha actual. Si la nueva fecha es resetearla, '0000-00-00', ponemos el campo 'Fecha no establecida' con clase danger, si es una fecha normal, ponemos la fecha con success
                    if (nueva_fecha == '0000-00-00') {
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).innerText = 'Fecha No establecida'; 
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).classList.remove('alerta-verde');
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).classList.add('alerta-roja');
                    } else {
                        //formamos la fecha formato dd-mm-yyy
                        var fecha_con_formato = nueva_fecha.split('-');
                        fecha_con_formato = [fecha_con_formato[2],fecha_con_formato[1],fecha_con_formato[0]].join('-');                               
                        
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).innerText = fecha_con_formato; 
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).classList.remove('alerta-roja');
                        document.querySelector('#span_fecha_'+id_producto+'_'+id_producto_atributo).classList.add('alerta-verde');
                    }
                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }


                    showSuccessMessage(data.message+' - '+data.resultado); 
                }
                else
                {      
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }
}


function mensajeDisponibilidad(e) {
    //console.log(e.target);
    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('guarda_available_later')){                    
        //para sacar el id_product y attribute, cogemos el id del botón, hacemos split y cogemos como id_attribute el último trozo, e id_product el penúltimo
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_producto = splitBotonId[splitBotonId.length - 2];
        var id_producto_atributo = splitBotonId[splitBotonId.length - 1];
        // console.log(id_producto);
        // console.log(id_producto_atributo);

        const nuevo_mensaje = document.querySelector('#text_available_later_'+id_producto+'_'+id_producto_atributo).value;
        // console.log(nuevo_mensaje);

        //mostramos spinner
        Spinner();

        //vamos a hacer una petición ajax a la función ajaxMensajeDisponibilidad en el controlador AdminProductosPrepedido que cambie el campo available_later del producto
        var dataObj = {};
        dataObj['id_product'] = id_producto;
        dataObj['mensaje'] = nuevo_mensaje;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=mensaje_disponibilidad" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax la confirmación de guardado del mensaje
                    console.dir(data.resultado);         
                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showSuccessMessage(data.message+' - '+data.resultado); 
                }
                else
                {                        
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    } 

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }
}


function categoriaPrepedido(e) {
    //console.log(e.target);
    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('toggle_prepedido')){                    
        //para sacar el id_product y attribute, cogemos el id del botón, hacemos split y cogemos como id_attribute el último trozo, e id_product el penúltimo
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_producto = splitBotonId[splitBotonId.length - 2];
        var id_producto_atributo = splitBotonId[splitBotonId.length - 1];
        // console.log(id_producto);
        // console.log(id_producto_atributo);

        //mostramos spinner
        Spinner();

        //tenemos que sacar del id la primera palabra para saber si es "eliminar" prepedido o "poner" prepedido
        var tarea = splitBotonId[0];
        // console.log(tarea);

        //vamos a hacer una petición ajax a la función ajaxCategoriaPrepedido en el controlador AdminProductosPrepedido que cambie la categoría de prepedido del producto
        var dataObj = {};
        dataObj['id_product'] = id_producto;
        dataObj['tarea'] = tarea;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=categoria_prepedido" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax la confirmación o no de haber cambiado la categoría prepedido
                    console.dir(data.resultado); 
                    //actualizamos el botón de poner / eliminar categoría, también el span de mensaje PREPEDIDO o NO PREPEDIDO
                    if (tarea == 'poner') {
                        //cambiamos clases del botón
                        document.querySelector('#'+botonId).classList.remove('btn-default');
                        document.querySelector('#'+botonId).classList.add('btn-basic');
                        //cambiamos title
                        document.querySelector('#'+botonId).title = 'QUITAR categoría PREPEDIDO al producto';
                        //cambiamos icono y texto en botón
                        document.querySelector('#'+botonId).innerHTML = '<i class="icon-minus"></i> Quitar Prepedido';
                        //cambiamos id al botón
                        document.querySelector('#'+botonId).id = 'eliminar_prepedido_'+id_producto+'_'+id_producto_atributo;
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_prepedido_'+id_producto+'_'+id_producto_atributo).innerText = 'PREPEDIDO'; 
                        document.querySelector('#span_prepedido_'+id_producto+'_'+id_producto_atributo).classList.add('alerta-naranja');
                    } else {                        
                        //hay que dejar el botón y el span como si no tuviera la categoría
                        //cambiamos clases del botón
                        document.querySelector('#'+botonId).classList.remove('btn-basic');
                        document.querySelector('#'+botonId).classList.add('btn-default');
                        //cambiamos title
                        document.querySelector('#'+botonId).title = 'PONER categoría PREPEDIDO al producto';
                        //cambiamos icono y texto en botón
                        document.querySelector('#'+botonId).innerHTML = '<i class="icon-plus"></i> Poner Prepedido';
                        //cambiamos id al botón
                        document.querySelector('#'+botonId).id = 'poner_prepedido_'+id_producto+'_'+id_producto_atributo;
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_prepedido_'+id_producto+'_'+id_producto_atributo).innerText = 'NO PREPEDIDO'; 
                        document.querySelector('#span_prepedido_'+id_producto+'_'+id_producto_atributo).classList.remove('alerta-naranja');
                    }       
                    
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showSuccessMessage(data.message+' - '+data.resultado); 

                }
                else
                {         
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }
}


function permitirPedidos(e) {
    //console.log(e.target);
    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('toggle_permitir')){                    
        //para sacar el id_product y attribute, cogemos el id del botón, hacemos split y cogemos como id_attribute el último trozo, e id_product el penúltimo
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_producto = splitBotonId[splitBotonId.length - 2];
        var id_producto_atributo = splitBotonId[splitBotonId.length - 1];
        // console.log(id_producto);
        // console.log(id_producto_atributo);

        //tenemos que sacar del id la primera palabra para saber si es "eliminar" permitir pedido o "poner" permitir pedido
        var tarea = splitBotonId[0];
        // console.log(tarea);

        //mostramos spinner
        Spinner();

        //vamos a hacer una petición ajax a la función ajaxPermitirPedidos en el controlador AdminProductosPrepedido que cambie out_of_stock del producto
        var dataObj = {};
        dataObj['id_product'] = id_producto;
        dataObj['tarea'] = tarea;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=permitir_pedidos" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax la confirmación o no de haber cambiado permitir pedidos
                    console.dir(data.resultado); 
                    //actualizamos el botón de poner / quitar permitir pedidos, también el span de mensaje SI PERMITE PEDIDOS o NO PERMITE PEDIDOS
                    if (tarea == 'poner') {
                        //cambiamos clases del botón
                        document.querySelector('#'+botonId).classList.remove('btn-default');
                        document.querySelector('#'+botonId).classList.add('btn-basic');
                        //cambiamos title
                        document.querySelector('#'+botonId).title = 'QUITAR permitir pedidos sin stock al producto';
                        //cambiamos icono y texto en botón
                        document.querySelector('#'+botonId).innerHTML = '<i class="icon-minus"></i> Quitar Permitir';
                        //cambiamos id al botón
                        document.querySelector('#'+botonId).id = 'eliminar_permitir_'+id_producto+'_'+id_producto_atributo;
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_permitir_'+id_producto+'_'+id_producto_atributo).innerText = 'SI PERMITE PEDIDOS'; 
                        document.querySelector('#span_permitir_'+id_producto+'_'+id_producto_atributo).classList.add('alerta-naranja');
                    } else {
                        //hay que dejar el botón y el span como si no tuviera permitir pedidos
                        //cambiamos clases del botón
                        document.querySelector('#'+botonId).classList.remove('btn-basic');
                        document.querySelector('#'+botonId).classList.add('btn-default');
                        //cambiamos title
                        document.querySelector('#'+botonId).title = 'PONER permitir pedidos sin stock al producto';
                        //cambiamos icono y texto en botón
                        document.querySelector('#'+botonId).innerHTML = '<i class="icon-plus"></i> Poner Permitir';
                        //cambiamos id al botón
                        document.querySelector('#'+botonId).id = 'poner_permitir_'+id_producto+'_'+id_producto_atributo;
                        //cambiamos texto al span, y le corregimos las clases
                        document.querySelector('#span_permitir_'+id_producto+'_'+id_producto_atributo).innerText = 'NO PERMITE PEDIDOS'; 
                        document.querySelector('#span_permitir_'+id_producto+'_'+id_producto_atributo).classList.remove('alerta-naranja');
                    }                                

                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showSuccessMessage(data.message+' - '+data.resultado); 

                }
                else
                {          
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
    }
}


function buscaPedidos(e) {
    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('revisar_producto')){                    
        //para sacar el id_product y attribute, cogemos el id del botón, hacemos split y cogemos como id_attribute el último trozo, e id_product el penúltimo
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_producto = splitBotonId[splitBotonId.length - 2];
        var id_producto_atributo = splitBotonId[splitBotonId.length - 1];
        var nombre_producto = document.querySelector('#nombre_'+id_producto+'_'+id_producto_atributo).innerText;
        // console.log(id_producto);
        // console.log(id_producto_atributo);
        // console.log(nombre_producto);

        //mostramos spinner
        Spinner();

        //vamos a hacer una petición ajax a la función ajaxPedidosProducto en el controlador AdminProductosPrepedido que muestre los pedidos en espera que contienen el producto seleccionado
        var dataObj = {};
        dataObj['id_product'] = id_producto;
        dataObj['id_product_attribute'] = id_producto_atributo;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=pedidos_producto" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                
                    //recibimos via ajax la info de los pedidos
                    console.dir(data.info_pedidos); 

                    //cambiamos el texto del panel-heading        
                    //document.querySelector('.panel-heading').innerHTML = 'Pedidos en espera para el producto - '+nombre_producto; //producto
                    document.querySelector('.panel-heading').innerHTML = `<span class="id_producto_pedidos" id="${id_producto+'_'+id_producto_atributo}">Pedidos en espera para el producto - ${nombre_producto}</span>`; 
                    
                    muestraPedidos(data.info_pedidos); 

                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showSuccessMessage(data.message); 

                }
                else
                {        
                    //eliminamos spinner
                    if (document.contains(document.querySelector('#spinner'))) {
                        document.querySelector('#spinner').remove();
                    }

                    showErrorMessage(data.message);
                }

            },
            error: function (jqXHR, textStatus, errorThrown)
            {
                showErrorMessage('ERRORS: ' + textStatus);
            }
        });  //fin ajax
        
    }
}

function muestraPedidos(pedidos) {
    //comprobamos si el panel de productos ya existe (se ha pulsado un botón antes) y si existe lo eliminamos para que no se añadan delante de nuevo
    if (document.contains(document.querySelector('#panel_productos'))) {
        document.querySelector('#panel_productos').remove();
    } 

    //comprobamos la existencia del  buscador, si está lo quitamos
    if (document.contains(document.querySelector('#buscador_panel'))) {
        document.querySelector('#buscador_panel').remove();
    }

    //comprobamos la existencia del panel de acciones de pedidos, lo colocamos si no está
    if (!document.contains(document.querySelector('#accion_pedidos_panel'))) {
        //creamos el panel con las acciones para pedidos que se mostrará al mostrar una lista de pedidos
        const panel_acciones_pedidos = document.createElement('div');
        panel_acciones_pedidos.classList.add('col-lg-6');
        panel_acciones_pedidos.id = "accion_pedidos_panel";
        panel_acciones_pedidos.innerHTML = `<div class="panel">
                                        <div class="clearfix">
                                            <h3>Acciones Globales Pedidos</h3>
                                            <div class="row container_botones">                                                
                                                <div class="col-lg-2 botones_acciones">
                                                    <button type="button" id="seleccionar_todos_pedidos" class="btn btn-default">
                                                        Seleccionar<br>todos
                                                    </button>
                                                </div>
                                                <div class="col-lg-2 botones_acciones">
                                                    <button type="button" id="deseleccionar_todos_pedidos" class="btn btn-default">
                                                        Deseleccionar<br>todos
                                                    </button>
                                                </div>
                                                <div class="col-lg-6 botones_acciones">
                                                    <label class="control-label">
                                                        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="Cambiará la fecha de disponibilidad avisada a los pedidos seleccionados" data-original-title="Nueva fecha de disponibilidad">
                                                            Nueva<br>disponibilidad
                                                        </span>
                                                    </label>
                                                    <input class="fecha_disponibilidad" id="nueva_fecha_pedidos_lista" type="date" name="nueva_fecha_pedidos_lista"  placeholder="dd-mm-yyyy" value="" min="1997-01-01" max="2030-12-31">
                                                    <button type="button" id="guarda_fecha_pedidos_lista" name="guarda_fecha_pedidos_lista" class="btn btn-sm btn-default guarda_fecha_disponibilidad_todos">
                                                        <i class="icon-save"></i> Guardar
                                                    </button>
                                                </div>
                                                <div class="col-lg-2 botones_acciones">
                                                    <button type="button" id="email_pedidos_seleccionados" class="btn btn-default" title="Enviará un email a los pedidos seleccionados">
                                                        Email<br>seleccionados
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>`;

        
        document.querySelector('#button_panel').appendChild(panel_acciones_pedidos);
        
        //ponemos un eventlistener para los cuatro botones del panel
        const seleccionar_todos = document.querySelector('#seleccionar_todos_pedidos');
        seleccionar_todos.addEventListener('click', seleccionaTodosPedidos);

        const deseleccionar_todos = document.querySelector('#deseleccionar_todos_pedidos');
        deseleccionar_todos.addEventListener('click', deseleccionaTodosPedidos);

        const guarda_fecha_seleccionados = document.querySelector('#guarda_fecha_pedidos_lista');
        guarda_fecha_seleccionados.addEventListener('click', function(){
            //hay que hacer la asignación del event listener así, porque si se hace :
            //guarda_fecha_seleccionados.addEventListener('click', guardaFechaPedidosSeleccionados('lista')) 
            //llama directamente a la función con el error consiguiente
            guardaFechaPedidosSeleccionados('lista');
        });

        const envia_email_seleccionados = document.querySelector('#email_pedidos_seleccionados');
        envia_email_seleccionados.addEventListener('click', enviaEmailPedidosSeleccionados);
    } 

    //deshabilitamos buscador
    //document.querySelector('#buscador').disabled = true;

    //vamos generando la lista/formulario de pedidos dinamicamente utilizando las clases (panel, label etc) de prestashop
    //creamos el div que contiene el formulario, lo añadiremos después del panel heading
    const form_wrapper = document.createElement('div');
    form_wrapper.classList.add('form-wrapper');
    form_wrapper.id = 'panel_pedidos';        
    
    document.querySelector('.panel-heading').insertAdjacentElement('afterend', form_wrapper);
    
    //var form_wrapper = '<div class="form-wrapper" id="panel_pedidos"></div>';
    //$('.panel-heading').after(form_wrapper);

    //contador para los pedidos
    var num_pedido = 0;

    //var productos = data.info_productos;
    pedidos.forEach(
        pedido => {

            num_pedido++;

            //formateamos fechas a dd-mm-yyyy
            //quitamos la hora de date_add partiendo primero por el espacio en blanco y de la primera parte que quede partiendo por -
            var fecha_pedido = pedido.fecha_pedido.split(' ')[0].split('-');
            fecha_pedido = [fecha_pedido[2],fecha_pedido[1],fecha_pedido[0]].join('-');
            //console.log(fecha_pedido);

            var available_date_compra = pedido.available_date_compra.split('-');
            available_date_compra = [available_date_compra[2],available_date_compra[1],available_date_compra[0]].join('-');

            var available_date_actual = pedido.available_date_actual.split('-');
            available_date_actual = [available_date_actual[2],available_date_actual[1],available_date_actual[0]].join('-');

            var fecha_disponibilidad = pedido.disponibilidad.split('-');
            fecha_disponibilidad = [fecha_disponibilidad[2],fecha_disponibilidad[1],fecha_disponibilidad[0]].join('-');

            var ultimo_aviso = pedido.disponibilidad_avisada.split('-');
            ultimo_aviso = [ultimo_aviso[2],ultimo_aviso[1],ultimo_aviso[0]].join('-');

            var fecha_ultimo_email = pedido.fecha_ultimo_aviso.split('-');
            fecha_ultimo_email = [fecha_ultimo_email[2],fecha_ultimo_email[1],fecha_ultimo_email[0]].join('-');

            if (pedido.fecha_ultimo_aviso_automatico == '0000-00-00') {
                var fecha_ultimo_email_automatico = 'Sin registros';
            } else {
                var fecha_ultimo_email_automatico = pedido.fecha_ultimo_aviso_automatico.split('-');
                fecha_ultimo_email_automatico = [fecha_ultimo_email_automatico[2],fecha_ultimo_email_automatico[1],fecha_ultimo_email_automatico[0]].join('-');
            }
            
            //contenido email
            if (pedido.contenido_email) {
                var contenido_ultimo_email = pedido.contenido_email;
            } else {
                var contenido_ultimo_email = 'No existen registros'
            }

            //otros productos sin stoc en pedido
            var otros_sin_stock = parseInt(pedido.productos_sin_stock_pedido) - 1;

            const casilla = document.createElement('div');
            casilla.classList.add('form-group','div_pedido');
            casilla.id = 'form_group_'+pedido.id_order; 
            casilla.innerHTML = `<br>
                    <div class='col-lg-12' id='info_pedido_${pedido.id_order}'>\
                    </div>`;   
            
            document.querySelector('#panel_pedidos').appendChild(casilla);

            //ponemos enlace al pedido y cliente en backoffice con la url obtenida en el controlador
                        
            var informacion_pedido = `<div class="panel clearfix panel_pedido"><h3><span style="font-size: 130%;"><a href="${pedido.url_pedido}" target="_blank" title="Ver Pedido" style="text-decoration: none;">${pedido.id_order}</a></span> - <span style="font-size: 150%;">${fecha_pedido}</span> - ${pedido.estado_actual} - <span title="Ver cliente ${pedido.id_customer}"><a href="${pedido.url_cliente}" target="_blank" style="text-decoration: none;">${pedido.nombre} ${pedido.apellido}</a></span><span class="pull-right texto_pedido_derecha">${pedido.modo_pago} - ${pedido.ciudad_entrega} - ${pedido.provincia} ( ${pedido.pais_entrega} )</span></h3>
                <div class="col-lg-1 checks text-center">
                    <label class="container_check">				
                        <input class="check_pedido" type="checkbox" name="checkboxes[]" value="${pedido.id_order}">					
                        <span class="checkmark"></span>
                    </label>
                </div>
                <div class="col-lg-2 text-center"> 
                    <b><span style="font-size: 130%;">- Info Compra -</span></b>                   
                    <div class="row text-center">
                        <p><b>Disponibilidad</b><br>
                        <span style="font-size: 200%;">${fecha_disponibilidad}</span> (${available_date_compra})</p>
                    </div>
                    <div class="row text-center">
                        <p><b>Mensaje</b><br>
                        ${pedido.mensaje_compra}</p>
                    </div>                    
                </div>
                <div class="col-lg-4 text-center"> 
                    <h4><span style="font-size: 130%;">- Disponibilidad de Producto -</span></h4> 
                    <div class="col-lg-6 text-center">
                        <p><b>Actual</b></p>
                        <div id='fecha_actual_${pedido.id_order}'>
                            <p><span style="font-size: 200%;">${available_date_actual}</span></p>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <p><b>Avisada</b></p>
                        <div id='fecha_avisada_${pedido.id_order}'>
                            <p><span style="font-size: 200%;">${ultimo_aviso}</span></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 text-center"> 
                    <b><span style="font-size: 130%;">- Otros datos -</span></b>                     
                    <p>Proveedor en compra:  <b>${pedido.proveedor}</b></p>
                    <p>Unidades en pedido:  <b>${pedido.cantidad}</b></p>
                    <p>Número productos:  <b>${pedido.productos_pedido}</b></p>
                    <p>Otros Sin Stock:  <b>${otros_sin_stock}</b></p>
                    <p>Pedidos Cliente:  <b>${pedido.pedidos_cliente}</b></p>
                </div>
                <div class="col-lg-1 text-center"> 
                    <b><span style="font-size: 130%;">- Último email aviso -</span></b>                     
                    <p id="fecha_ultimo_email_${pedido.id_order}">Fecha envío:  <br><span style="font-size: 120%;"><b>${fecha_ultimo_email}</b></span></p> 
                    <p>Recordatorio automático:  <br><span style="font-size: 120%;"><b>${fecha_ultimo_email_automatico}</b></span></p>                    
                </div>
                <div class="col-lg-2 text-center"> 
                    <b><span style="font-size: 130%;">- Contenido último email -</span></b>                     
                    <p class='scroll_email'>${contenido_ultimo_email}</p>               
                </div>
            </div>`;

            document.querySelector('#info_pedido_'+pedido.id_order).innerHTML = informacion_pedido;

            //comprobamos si fecha actual del producto y fecha avisada coinciden, ponemos success o danger según sea al div que contiene la fecha
            //sacamos el contenido del span dentro del p dentro del div con id bla bla
            const fecha_avisada = document.querySelector('#fecha_avisada_'+pedido.id_order+' > p > span').innerText;
            const fecha_actual = document.querySelector('#fecha_actual_'+pedido.id_order+' > p > span').innerText;
            if (fecha_avisada != fecha_actual) {
                document.querySelector('#fecha_avisada_'+pedido.id_order).classList.add('alerta-roja');
                document.querySelector('#fecha_actual_'+pedido.id_order).classList.add('alerta-naranja');
            } else {
                document.querySelector('#fecha_avisada_'+pedido.id_order).classList.add('alerta-verde');
                document.querySelector('#fecha_actual_'+pedido.id_order).classList.add('alerta-verde');
            }
            
        }
    );

    //cambiamos el texto del panel-heading        
    document.querySelector('.panel-heading').innerHTML = num_pedido+' '+document.querySelector('.panel-heading').innerHTML;

}

//deberá buscar en aquellos que tengan display inline en el momento, para no chocar con los filtros. Los que tengan display none en el momento de la búsqueda no podrán pasar a inline, porque manda el filtro. Si la búsqueda da x productos, se dejarán inline los que estén inline y coincidan, si el producto coincide pero esta display none, se dejará así. Si el producto no coincide y está inline, se pasará a none.
function buscaProducto() {
    //this.value es lo que se va introduciendo en el buscador
    // console.log('buscando');
    // console.log(this.value);
    
    //guardamos y pasamos minúsculas y quitamos espacios vacios de laterales
    const busqueda = this.value.toLowerCase().trim();
    
    //console.log(array_productos);

    //buscamos en el array_productos coincidencias con cualquiera de los 4 datos almacenados de cada producto, si coincide con alguno le ponemos display inline, sino none    
    Object.entries(array_productos).forEach(([key, value]) => {
        //console.log(key);
        let coincide = 0;
        //por cada key, que es el id del producto, iteramos cada dato (si lo hay, pej puede no tener ean) y comprobamos si contiene busqueda
        value.forEach(dato => {            
            if (dato && dato.toLowerCase().includes(busqueda)) {
                //console.log('coincide '+dato);
                coincide = 1;
            }             
        });

        //si el producto coincide y esta inline, se deja así. Si no coincide y está inline, se pasa a none. Si coincide y está none, se deja none. Es decir, solo se ocultan productos que no coinciden. Si coincide no se toca nada.
        if (!coincide) {
            if (document.querySelector('#form_group_'+key).style.display == 'inline') {
                document.querySelector('#form_group_'+key).style.display = 'none';
            }            
        } 

        // if (coincide) {
        //     document.querySelector('#form_group_'+key).style.display = 'inline';
        // } else {
        //     document.querySelector('#form_group_'+key).style.display = 'none';
        // }
    });

    //MUY LENTOOOO
    // //buscamos todos los elementos de clase div_producto, que es cada div que contiene un producto
    // const productos = document.querySelectorAll('.div_producto');

    // productos.forEach( item => {
    //     //por cada producto sacamos su nombre, ean13, referencia prestashop y referencia proveedor
    //     //console.log(item.id);
    //     var div_el_producto = document.querySelector('#'+item.id);
    //     //sacamos el id_product+id_product_attribute correspondiente al producto
    //     var splitDivId = item.id.split('_');
    //     var id_el_producto = splitDivId[splitDivId.length - 2]+'_'+splitDivId[splitDivId.length - 1];  
    //     //console.log(div_el_producto);
    //     var nombre_producto = document.querySelector('#nombre_'+id_el_producto).innerText.toLowerCase();        
    //     var ref_presta_producto = document.querySelector('#ref_presta_'+id_el_producto).innerText.toLowerCase();        
    //     var ref_prov_producto = document.querySelector('#ref_prov_'+id_el_producto).innerText.toLowerCase();        
    //     var ean_producto = document.querySelector('#ean_'+id_el_producto).innerText.toLowerCase();
        
    //     //comparamos en minúsculas la cadena que viene del input del buscador con cada elemento del producto, si está dentro de alguno le ponemos display inline, si no está en ninguno ponemos none. Si viene número es como string
    //     if (nombre_producto.includes(busqueda) || ref_presta_producto.includes(busqueda) || ref_prov_producto.includes(busqueda) || ean_producto.includes(busqueda)) {
    //         div_el_producto.style.display = 'inline';
    //     } else {
    //         div_el_producto.style.display = 'none';
    //     }
        
    // });
}

//filtrar lista de productos según el proveedor seleccionado, si permite o no pedidos, o si tiene stock físico. el array array_filtros_aplicados contiene los filtros que hemos seleccionado (si hemos quitado alguno también se llama a la función) y se mostrarán los productos que cumplan los filtros
// el array de los productos es: array_filtros_producto[el_producto] = [producto.proveedor, permite_pedido, tiene_stock];
// el_producto es id_product - id_product_attribute
// producto.proveedor es un nombre, permite_pedido es si o no y stock es si o no. Según filtro usaremos un indice del array para buscar y con busqueda indicamos que coincidencia debe tener
function filtraProductos() { 
    console.log(array_filtros_aplicados);   
    
    var proveedor_filtro = '';

    var indices_busquedas = [];
    
    if (array_filtros_aplicados.includes('proveedor')) {
        proveedor_filtro = document.querySelector('#proveedores').value;
        // console.log(proveedor_filtro);
        indices_busquedas[0] = proveedor_filtro;
    } 

    if (array_filtros_aplicados.includes('si_permite')) {
        indices_busquedas[1] = 'si';
    } else if (array_filtros_aplicados.includes('no_permite')) {
        indices_busquedas[1] = 'no';
    }
    
    if (array_filtros_aplicados.includes('con_stock')) {
        indices_busquedas[2] = 'si';
    } else if (array_filtros_aplicados.includes('sin_stock')) {
        indices_busquedas[2] = 'no';
    } 

    console.log(indices_busquedas);    

    var resultados = 0;

    Object.entries(array_filtros_producto).forEach(([key, value]) => {
        //value es un array por producto, que contiene [producto.proveedor, permite_pedido, tiene_stock]. key es parte del identificador del producto para localizarlo en el DOM
        //console.log(key);
        let coincide = 0;

        indices_busquedas.forEach(
            (valor, index) => {
                if (value[index] == valor) {
                    // console.log('coincide');
                    // console.log('value[index]='+value[index]+' busqueda='+valor);
                    coincide++;
                }  
            }           
        );              

        //comprobamos el número de filtros que había en indices_busquedas y si es el mismo con los que han coincidido. array.length devuelve el nª incluyendo los empty, por lo que usamos Object.keys(objeto).lenght
        if (Object.keys(indices_busquedas).length == coincide) {
            // console.log('coinciden todas '+coincide+' key '+key);
            document.querySelector('#form_group_'+key).style.display = 'inline';
            resultados++;
        } else {
            // console.log('NO coinciden todas '+coincide);
            document.querySelector('#form_group_'+key).style.display = 'none';
        }
    });

    console.log('resultados='+resultados)
          
}

//marcar todos los checks de pedidos
function seleccionaTodosPedidos() {
    // console.log('seleccionaTodosPedidos');

    //recoger todos los checks de pedido
    document.querySelectorAll('.check_pedido').forEach( item => {
        item.checked = true;             
    });
}

//desmarcar todos los checks de pedidos
function deseleccionaTodosPedidos() {
    // console.log('deseleccionaTodosPedidos');

    //recoger todos los checks de pedido
    document.querySelectorAll('.check_pedido').forEach( item => {
        item.checked = false;             
    });
}

//guarda / cambia fecha de disponibilidad para todos los pedidos seleccionados
function guardaFechaPedidosSeleccionados(origen) {
    // console.log('origen '+origen);
    //creamos la variable fuera de los if
    var nueva_fecha_disponibilidad = '';
    //obtenemos la fecha seleccionada. La llamada puede venir desde el botón guardar del panel de botones, en cuyo caso la fecha es la del input date con id =nueva_fecha_pedidos_lista. La llamada a esta función también puede venir desde dentro de la función de envío de email enviarEmailPedidos si se ha introducido fecha en el input date del cuadro de email. Comprobamos si en el documento existe el input date con id nueva_fecha_pedidos_email y si este es visible (comprobando si es visible el cuadro de email), si es así tiene preferencia sobre el otro y utilizamos el valor indicado en el. Si pulsamos el botón de Guardar la fecha del panel de botones estando abierto el cuadro de email, mostramos error por seguridad.
    if (document.contains(document.querySelector('#nueva_fecha_pedidos_email'))) {
        console.log('está la ventana');
        if (window.getComputedStyle(document.querySelector('#cuadro_email')).display === "none") {
            console.log('está la ventana pero invisible');
            //el cuadro de email ha sido generado pero está cerrado, usamos la fecha del input date de fuera
            nueva_fecha_disponibilidad = document.querySelector('#nueva_fecha_pedidos_lista').value;
            console.log('#nueva_fecha_pedidos_lista invisible='+nueva_fecha_disponibilidad);

        } else {
            console.log('está la ventana y VISIBLE');
            //comprobamos si se ha llamado desde el cuadro email o desde la la botonera de lsita. Si es está última mostramos error
            if (origen == 'lista') {
                showErrorMessage('Cierra el cuadro de emails para guardar esta fecha');
                return;
            } else {
                //el cuadro de email ha sido generado y está visible en la pantalla, además se ha llamado a la función para guardar la fecha desde dicho cuadro, usamos la fecha del input date del cuadro
                nueva_fecha_disponibilidad = document.querySelector('#nueva_fecha_pedidos_email').value;
                console.log('#nueva_fecha_pedidos_email='+nueva_fecha_disponibilidad);
            }            
        }     
    } else {
        console.log('NO está la ventana y no ha sido generada');
        //el cuadro de email aún no ha sido generado, usamos la fecha del input date de fuera
        nueva_fecha_disponibilidad = document.querySelector('#nueva_fecha_pedidos_lista').value;
        console.log('#nueva_fecha_pedidos_lista='+nueva_fecha_disponibilidad);
    }    

    //comprobamos quehaya fecha y que la fecha sea posterior a la fecha actual. Generando objetos se pueden compara con < y > (no con == etc)
    if (!nueva_fecha_disponibilidad) {
        showErrorMessage('No hay fecha seleccionada');
        return;
    } else {
        const hoy = new Date();
        const nueva = new Date(nueva_fecha_disponibilidad);
        if ((nueva - hoy) < 0)  {
            //la fecha es anterior, no continuamos
            showErrorMessage('La fecha seleccionada ya ha pasado');
            return;
        }
    }

    //mostramos spinner
    if (!document.contains(document.querySelector('#spinner'))) {
        Spinner();
    }
    
    //sacamos los id del producto para identificar la línea en tabla productos_vendidos_sin_stock (id_order + id_product + id_product_attribute)
    const ids_producto = document.querySelector('.id_producto_pedidos').id;
    const id_producto = ids_producto.split('_')[0];
    const id_producto_atributo = ids_producto.split('_')[1];
    console.log(id_producto+' - '+id_producto_atributo);

    //obtenemos los pedidos con check
    var array_orders = [];
    const pedidos_check = document.querySelectorAll('.check_pedido:checked');
    if (pedidos_check.length <= 0) {
        //no se ha seleccionado ningún pedido
        showErrorMessage('No hay pedidos seleccionados');
        return;
    }
    pedidos_check.forEach( item => {
        //console.log(item.value); 
        //introducimos el id como número
        array_orders.push(Number(item.value));      
        
    });
    console.log(array_orders); 
    //cambiamos la fecha mediante ajax en la tabla lafrips_productos_vendidos_sin_stock. Enviamos los id de producto, la nueva fecha y un array con los id_order de los pedidos a cambiar    
    var dataObj = {};
    dataObj['id_product'] = id_producto;
    dataObj['id_product_attribute'] = id_producto_atributo;
    dataObj['fecha'] = nueva_fecha_disponibilidad;
    dataObj['pedidos'] = array_orders;
    //el token lo hemos sacado arriba del input hidden
    $.ajax({
        url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=fecha_disponibilidad_avisada" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        
        {
            if (typeof data.error === 'undefined')
            {                
                //recibimos via ajax la confirmación o no de haber cambiado las fechas
                console.dir(data.resultado); 

                //recoger todos los checks de pedido y desseleccionarlos
                document.querySelectorAll('.check_pedido').forEach( item => {
                    item.checked = false;             
                });
                
                //formamos la fecha formato dd-mm-yyy
                var nueva_fecha_disponibilidad_con_formato = nueva_fecha_disponibilidad.split('-');
                nueva_fecha_disponibilidad_con_formato = [nueva_fecha_disponibilidad_con_formato[2],nueva_fecha_disponibilidad_con_formato[1],nueva_fecha_disponibilidad_con_formato[0]].join('-');   

                //cambiamos la fecha avisada en la vista de pedidos, cambiando en su caso los alerts de los divs que las contienen
                pedidos_check.forEach( item => {
                    
                    document.querySelector(`#fecha_avisada_${item.value} > p > span`).innerText = nueva_fecha_disponibilidad_con_formato;
                    
                    //comprobamos si fecha actual del producto y fecha avisada coinciden, ponemos success o danger según sea al div que contiene la fecha
                    //sacamos el contenido del span dentro del p dentro del div con id bla bla
                    // const fecha_avisada = document.querySelector(`#fecha_avisada_${item.value} > p > span`).innerText;
                    var fecha_actual_producto = document.querySelector(`#fecha_actual_${item.value} > p > span`).innerText;
                    
                    if (nueva_fecha_disponibilidad_con_formato !== fecha_actual_producto) {
                        //primero quitamos las clases a los divs
                        document.querySelector(`#fecha_avisada_${item.value}`).className = '';
                        document.querySelector(`#fecha_actual_${item.value}`).className = '';
                        //añadimos las que tocan
                        document.querySelector(`#fecha_avisada_${item.value}`).classList.add('alert', 'alert-danger');
                        document.querySelector(`#fecha_actual_${item.value}`).classList.add('alert', 'alert-warning');
                    } else {
                        //primero quitamos las clases a los divs
                        document.querySelector(`#fecha_avisada_${item.value}`).className = '';
                        document.querySelector(`#fecha_actual_${item.value}`).className = '';
                        //añadimos las que tocan
                        document.querySelector(`#fecha_avisada_${item.value}`).classList.add('alert', 'alert-success');
                        document.querySelector(`#fecha_actual_${item.value}`).classList.add('alert', 'alert-success');
                    }
                    
                });

                //eliminamos spinner
                if (document.contains(document.querySelector('#spinner'))) {
                    document.querySelector('#spinner').remove();
                }

                showSuccessMessage(data.message); 

            }
            else
            {                
                //eliminamos spinner
                if (document.contains(document.querySelector('#spinner'))) {
                    document.querySelector('#spinner').remove();
                }

                showErrorMessage(data.message);
            }

        },
        error: function (jqXHR, textStatus, errorThrown)
        {
            showErrorMessage('ERRORS: ' + textStatus);
        }
    });  //fin ajax              
    

}

//envia Email a los Pedidos con check
function enviaEmailPedidosSeleccionados() {
    // console.log('enviaEmailPedidosSeleccionados');

    //obtenemos los pedidos con check    
    if (document.querySelectorAll('.check_pedido:checked').length <= 0) {
        //no se ha seleccionado ningún pedido
        showErrorMessage('No hay pedidos seleccionados');
        return;
    }     

    //chequeamos si ya existe el cuadro de email, si se ha generado antes y solo tiene display none, si es así le ponemos display block, si no lo generamos
    if (document.contains(document.querySelector('#cuadro_email'))) {
        document.querySelector('#cuadro_email').style.display = "block";
    } else {
        //mostramos cuadro para texto del email, permitiendo escoger si al mismo tiempo se desea cambiar la fecha de aviso del producto/pedido
        const cuadro_email = document.createElement('div');    
        cuadro_email.id = 'cuadro_email'; 
        cuadro_email.classList.add('col-lg-6');    
        cuadro_email.innerHTML = `<div class="panel clearfix">
                                    <h3 id="cuadro_email_header">Email<span id="cierra_cuadro_email" class="pull-right">X</span></h3> 
                                    <div class="col-lg-12 text-center"> 
                                        <div class="row">
                                            <label class="control-label">
                                                <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="Dejar en blanco para no modificar la fecha" data-original-title="Nueva fecha de disponibilidad">
                                                    Introduce una fecha si deseas cambiarsela a todos los pedidos seleccionados
                                                </span>
                                            </label> 
                                            <input class="fecha_disponibilidad" id='nueva_fecha_pedidos_email' type="date" placeholder="dd-mm-yyyy" value="" min="1997-01-01" max="2030-12-31">                                        
                                        </div>
                                        <br>                                                      
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-1">                                        
                                        </div>
                                        <div class="col-lg-10">
                                            <br>
                                            <h1>Contenido Email</h1>
                                            <h2>Hola <span style="color:#d69e9a;"><i>"Nombre Cliente"</i></span>, te escribo en relación a tu pedido en La Frikilería con referencia <span style="color:#d69e9a;"><i>"Nº pedido"</i></span> que nos hiciste el pasado día <span style="color:#d69e9a;"><i>"Fecha pedido"</i></span></h2>
                                            <p>	Como sabes, estamos a la espera de recibir productos del proveedor para completar tu pedido y hacerte el envío.</p>
                                            <p>Tenemos noticias sobre el producto <span style="color:#d69e9a;"><i>"Nombre de producto"</i></span></p>
                                            <br>

                                            <textarea id="texto_email" rows="10" placeholder="Escribe aquí la información concreta del retraso o problema sin escatimar fechas concretas ni detalles. Un ejemplo: 
                                            Nos comunica el proveedor que la nueva fecha estimada de llegada del producto es dd/mm/aaaa. Según nos indican, el retraso es debido a un problema del fabricante que se ha pillado los dedos porque no han recibido las chinchetas para fijar las alas que vienen de Myanmar, donde ya sabes que recientemente ha habido inundaciones y chinches.
                                            Lo sentimos mucho y te pedimos disculpas por este retraso producido por causas totalmente ajenas a nosotros.
                                            Tú no tienes que hacer nada. En cuanto recibamos los productos del proveedor te haremos el envío por mensajería urgente 24h.
                                            Si este cambio de fechas te causa un problema y ves que no puedes esperar más o necesitas cambiar algo del pedido, simplemente contesta a este email y nos pondremos en contacto contigo para buscarte una solución.
                                            "></textarea>

                                            <br>                                            
                                            <p>Un saludo</p>
                                        </div>
                                        <div class="col-lg-1">                                        
                                        </div>
                                    </div>
                                    <br>                                    
                                    <div class="row text-center">
                                        <button type="button" id='envia_email_pedidos' class="btn btn-default" title="Enviará el email a los pedidos">
                                            Enviar Email
                                        </button>
                                    </div>  
                                </div>`; 
                                
                                // cuadro_email.innerHTML = `<div id="cuadro_email_header">Click here to move</div>
                                // <p>Move</p>
                                // <p>this</p>
                                // <p>DIV</p>`;
        
        //hacemos append del nodo del cuadro a #content,. Se inserta oculto y le damos display block
        const content = document.querySelector('#content');
        // content.insertBefore(cuadro_email, document.querySelector('#button_panel'));  
        content.append(cuadro_email);  
        cuadro_email.style.display = "block";

        //hacer el DIV del cuadro email movible, llamamos a la función dragElement directamente con el cuadro como argumento:
        dragElement(document.querySelector('#cuadro_email'));

        //si se pulsa la X de cierre, hacemos display none
        document.querySelector('#cierra_cuadro_email').addEventListener('click', cierraCuadroEmail );

        //añadimos eventlistener al botón de enviar email que llamará a la función que pide por ajax los emails y cambios de fecha necesarios       
        document.querySelector('#envia_email_pedidos').addEventListener('click', enviaEmailPedidos);
        
    }

}

function cierraCuadroEmail (){
    document.querySelector('#cuadro_email').style.display = "none";
}

//función a la que se envía el elemento cuadro de email como argumento. Si se hace mousedown sobre cuadro_email_header comienza a actuar sobre cuadro_email
function dragElement(elmnt) {       
    var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;          
    document.querySelector('#'+elmnt.id + "_header").addEventListener("mousedown", dragMouseDown);           

    function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();
        // get the mouse cursor position at startup:
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        // call a function whenever the cursor moves:
        document.onmousemove = elementDrag;
    }
    
    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        // calculate the new cursor position:
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        // set the element's new position:
        elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
        elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
    }
    
    function closeDragElement() {
        /* stop moving when mouse button is released:*/
        document.onmouseup = null;
        document.onmousemove = null;
    }
}


function enviaEmailPedidos() {
    console.log('enviaEmailPedidos');

    var cambiar_fecha = 0;

    //obtenemos los pedidos con check
    var array_orders = [];
    if (document.querySelectorAll('.check_pedido:checked').length <= 0) {
        //no se ha seleccionado ningún pedido
        showErrorMessage('No hay pedidos seleccionados');
        return;
    }     
    document.querySelectorAll('.check_pedido:checked').forEach( item => {
        // console.log(item.value); 
        //introducimos el id como número
        array_orders.push(Number(item.value));      
        
    });
    console.log(array_orders);   
    
    const texto_email = document.querySelector('#texto_email').value;
    console.log(texto_email);

    if (!texto_email || texto_email == '' || texto_email.length < 10) {
        console.log('texto inexistente o demasiado corto?');
        showErrorMessage('El email no tiene contenido o es demasiado corto');
        return;
    }
   

    //tenemos texto para el email, comprobamos que haya fecha y si la hay primero llamamos a guardaFechaPedidosSeleccionados para que haga el cambio
    if (document.querySelector('#nueva_fecha_pedidos_email').value && document.querySelector('#nueva_fecha_pedidos_email').value != '') {
        console.log('hay fecha '+document.querySelector('#nueva_fecha_pedidos_email').value);
        guardaFechaPedidosSeleccionados('email');
        //ERROR AQUÍ si falla el proceso de guardar fecha o la fecha es errónea, el proceso del email sigue igualmente

    } else {
        console.log('NO hay fecha ');
    }    

    //después de procesar la fecha si la había, llamamos por ajax para enviar los emails
    //mostramos spinner
    if (!document.contains(document.querySelector('#spinner'))) {
        Spinner();
    }
    
    //Enviamos el id de producto, y un array con los id_order de los pedidos a cuyos clientes enviar el email además del texto para el email
    //sacamos los id del producto para identificar la línea en tabla productos_vendidos_sin_stock (id_order + id_product + id_product_attribute)
    const ids_producto = document.querySelector('.id_producto_pedidos').id;
    const id_producto = ids_producto.split('_')[0];  
    const id_producto_atributo = ids_producto.split('_')[1];    
    console.log(id_producto);

    var dataObj = {};
    dataObj['id_product'] = id_producto;  
    dataObj['id_product_attribute'] = id_producto_atributo;    
    dataObj['pedidos'] = array_orders;
    dataObj['texto'] = texto_email;
    //el token lo hemos sacado arriba del input hidden
    $.ajax({
        url: 'index.php?controller=AdminProductosPrepedido' + '&token=' + token + "&action=envia_email" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        
        {
            if (typeof data.error === 'undefined')
            {                
                //recibimos via ajax la confirmación o no de haber enviado los emails
                console.dir(data.resultado); 

                //recoger todos los checks de pedido y desseleccionarlos (si se cambió también fecha ya lo estarán)
                document.querySelectorAll('.check_pedido').forEach( item => {
                    item.checked = false;             
                });

                //formamos la fecha formato dd-mm-yyyy
                const hoy = new Date();        
                const fecha_hoy = String(hoy.getDate()).padStart(2,'0')+'-'+String((hoy.getMonth()+1)).padStart(2,'0')+'-'+hoy.getFullYear();          

                //cambiamos la fecha de último email de aviso en la vista de pedidos, actualizando a la fecha de hoy, y ponemos el contenido del email en su sitio
                array_orders.forEach( id_order => {
                    // console.log(id_order);
                    document.querySelector(`#fecha_ultimo_email_${id_order} > span > b`).innerText = fecha_hoy; //fecha
                    document.querySelector(`#info_pedido_${id_order} p.scroll_email`).innerText = texto_email; //contenido email                
                });

                //escondemos el cuadro de email, limpiando textarea y fecha si hay.
                document.querySelector('#nueva_fecha_pedidos_email').value = '';
                document.querySelector('#texto_email').value = '';
                cierraCuadroEmail();

                //eliminamos spinner
                if (document.contains(document.querySelector('#spinner'))) {
                    document.querySelector('#spinner').remove();
                }

                showSuccessMessage(data.message); 

            }
            else
            {          
                //eliminamos spinner
                if (document.contains(document.querySelector('#spinner'))) {
                    document.querySelector('#spinner').remove();
                }

                showErrorMessage(data.message);
            }

        },
        error: function (jqXHR, textStatus, errorThrown)
        {
            showErrorMessage('ERRORS: ' + textStatus);
        }
    });  //fin ajax  

    
    
}

//spinner de carga
function Spinner() {
    console.log('función spinner');
    //nos aseguramos de que no haya un spinner presente
    if (document.contains(document.querySelector('#spinner'))) {
        document.querySelector('#spinner').remove();
    }

    const botonera = document.querySelector('#button_panel');
    // console.log(botonera);
  
    const divSpinner = document.createElement('div');
    divSpinner.id = 'spinner';
    divSpinner.classList.add('sk-circle');
  
    divSpinner.innerHTML = `
        <div class="sk-circle1 sk-child"></div>
        <div class="sk-circle2 sk-child"></div>
        <div class="sk-circle3 sk-child"></div>
        <div class="sk-circle4 sk-child"></div>
        <div class="sk-circle5 sk-child"></div>
        <div class="sk-circle6 sk-child"></div>
        <div class="sk-circle7 sk-child"></div>
        <div class="sk-circle8 sk-child"></div>
        <div class="sk-circle9 sk-child"></div>
        <div class="sk-circle10 sk-child"></div>
        <div class="sk-circle11 sk-child"></div>
        <div class="sk-circle12 sk-child"></div>
    `;

    // console.log(divSpinner);
    botonera.appendChild(divSpinner);
}

