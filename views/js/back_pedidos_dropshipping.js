/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

//para mostrar el botón de scroll arriba, aparecerá cuando se haga scroll abajo y desaparecerá al volver arriba
$(window).scroll(function(){    
    if ($(this).scrollTop() > 400) {
      $('#boton_scroll').fadeIn();
    } else {
      $('#boton_scroll').fadeOut();
    }
});

document.addEventListener('DOMContentLoaded', start);

function start() {
    //quitamos cosas del panel header que pone Prestashop por defecto, para que haya más espacio. 
    document.querySelector('h2.page-title').remove(); 
    document.querySelector('div.page-bar.toolbarBox').remove(); 
    document.querySelector('div.page-head').style.height = '36px';  
    
    //el panel que contiene el formulario, etc donde aparecerá el contenido lo hacemos relative y colocamos para que aparezca inicialmente bajo el panel superior, poniendo top -80px ¿?
    const panel_contenidos = document.querySelector('div#content div.row'); 
    panel_contenidos.style.position = 'relative';
    panel_contenidos.style.top = '-80px';    

    //al div con id fieldset_0 que viene a contener todo esto, le añadimos clase clearfix para que la tabla etc quede siempre dentro
    const panel_fieldset_0 = document.querySelector('div#fieldset_0'); 
    panel_fieldset_0.classList.add('clearfix');    

    // obtenemos token del input hidden que hemos creado con id 'token_admin_modulo_'.$token_admin_modulo, para ello primero buscamos el id de un input cuyo id comienza por token_admin_modulo y al resultado le hacemos substring.  
    const id_hiddeninput = document.querySelector("input[id^='token_admin_modulo']").id;    

    //substring, desde 19 hasta final(si no se pone lenght coge el resto de la cadena)
    const token = id_hiddeninput.substring(19);
    // console.log('token = '+token);

    //vamos a añadir un panel para visualizar los pedidos, llamado div_pedidos, lo creamos y ponemos adjunto antes que la tabla, de modo que se desplaza a la derecha al poner el panel de tabla
    //div para mostrar los pedidos
    const div_pedidos = document.createElement('div');
    div_pedidos.classList.add('clearfix','col-lg-7');
    div_pedidos.id = 'div_pedidos';
    document.querySelector('div.panel-heading').insertAdjacentElement('afterend', div_pedidos);

    //generamos la tabla "vacia" para los resultados de las consultas
    //utilizamos el mismo formato de prestashop para mostrar los productos, con tabla responsiva etc.
    //div contenedor de la tabla
    const div_tabla = document.createElement('div');
    div_tabla.classList.add('table-responsive-row','clearfix','col-lg-5');
    div_tabla.id = 'div_tabla';
    document.querySelector('div.panel-heading').insertAdjacentElement('afterend', div_tabla);    

    //generamos tabla
    const tabla = document.createElement('table');
    tabla.classList.add('table');
    tabla.id = 'tabla';
    document.querySelector('#div_tabla').appendChild(tabla);

    //generamos head de tabla
    const thead = document.createElement('thead');
    thead.id = 'thead';
    thead.innerHTML = `
        <tr class="nodrag nodrop" id="tr_campos_tabla">
            <th class="fixed-width-xs row-selector text-center">
                <input class="noborder" type="checkbox" name="selecciona_todos_pedidos" id="selecciona_todos_pedidos">
            </th>            
            <th class="fixed-width-sm center">
                <span class="title_box active">ID
                    <a id="orden_id_abajo" class="filtro_orden orden_activo"><i class="icon-caret-down"></i></a>
                    <a id="orden_id_arriba" class="filtro_orden"><i class="icon-caret-up"></i></a>
                </span>
            </th>    
            <th class="fixed-width-xl text-center">
                <span class="title_box">Referencia
                </span>
            </th>        
            <th class="fixed-width-xl text-center">
                <span class="title_box">Proveedor
                </span>
            </th>
            <th class="fixed-width-xl text-center">
                <span class="title_box">Estado
                </span>
            </th> 
            <th class="fixed-width-xl text-center">
                <span class="title_box">Envío
                </span>
            </th>                        
            <th class="fixed-width-sm text-center">
                <span class="title_box">Fecha
                    <a id="orden_fecha_abajo" class="filtro_orden"><i class="icon-caret-down"></i></a>
                    <a id="orden_fecha_arriba" class="filtro_orden"><i class="icon-caret-up"></i></a>
                </span>
            </th>            
            <th colspan="2" class="fixed-width-md text-center">Paginación</th>            
        </tr>
        <tr class="nodrag nodrop filter row_hover">
            <th class="text-center">--</th>
            <th class="text-center"><input type="text" class="filter" id="filtro_id" value=""></th> 
            <th class="text-center">--</th> 
            <th class="text-center">
                <select class="filter center"  name="filtro_proveedor" id="filtro_proveedor">                                                                                       
                </select>
            </th>            
            <th class="text-center">
                <select class="filter center" name="filtro_estado"  id="filtro_estado">
                    <option value="0" selected="selected">-</option>
                    <option value="1">Error</option>  
                    <option value="2">Cancelado</option>   
                    <option value="3">Pendiente</option>   
                    <option value="4">Aceptado</option>    
                    <option value="5">Finalizado</option>                                                                    
                </select>
            </th>  
            <th class="text-center">--</th>
            <th class="text-right">
				<div class="row">
                    <div class="input-group fixed-width-md center">
                        <input class="input_date" id="filtro_desde" type="text" placeholder="Desde" name="pedidos_desde" value="" min="1997-01-01" max="2030-12-31" onfocus="(this.type='date')" onblur="if(this.value==''){this.type='text'}"> 
                    </div>
                    <div class="input-group fixed-width-md center">
                        <input class="input_date" id="filtro_hasta" type="text" placeholder="Hasta" name="pedidos_hasta" value="" min="1997-01-01" max="2030-12-31" onfocus="(this.type='date')" onblur="if(this.value==''){this.type='text'}">                        
                    </div>										
                </div>
			</th>                        
            <th class="text-left" colspan="2">
                <div class="row">
                    <div class="text-center col-md-6 col-lg-6 col-sm-6">
                        <select class="filter center" name="filtro_limite_pedidos"  id="filtro_limite_pedidos">
                            <option value="20" selected="selected">20</option>
                            <option value="50">50</option>  
                            <option value="100">100</option>   
                            <option value="500">500</option>   
                            <option value="0">Todos</option>
                        </select>	
                    </div>
                    <div class="col-md-6 col-lg-6 col-sm-6">
                        / <span id="total_pedidos">125</span>
                    </div>													
                </div>
                <div class="row">
                    <div class="text-center">
                        <ul class="pagination center">
                            <li>
                                <a id="pagination_left_left" class="flechas_paginacion">
                                    <i class="icon-double-angle-left"></i>
                                </a>
                            </li>
                            <li>
                                <a id="pagination_left" class="flechas_paginacion">
                                    <i class="icon-angle-left"></i>
                                </a>
                            </li>
                            <li>
                                <a id="page_number" class="deshabilita_paginador">
                                    <span id="numero_pagina">1</span>
                                </a>
                            </li>	
                            <li>
                                <a id="pagination_right" class="flechas_paginacion">
                                    <i class="icon-angle-right"></i>
                                </a>
                            </li>
                            <li>
                                <a id="pagination_right_right" class="flechas_paginacion">
                                    <i class="icon-double-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </th>            
        </tr>
        `; 
    document.querySelector('#tabla').appendChild(thead);

    //los inputs de fecha en lugar de type date, que obliga a placeholder tipo dd-mm-yyyy los pongo type text, que permite cambiar placeholder, y añadimos un onfocus que lo cambia a type date y un onblur, que si su value es '' lo devuelve a type text al salir el ratón

    //llamamos a función para rellenar select de proveedores
    obtenerProveedores();

    //añadimos eventlistener a las flechas de ordenar (id de producto, fehcas.. que tienen clase común filtro_orden)
    //30/08/2022 Para "recordar" la seleccionada se le añade al <a> pulsado la calse "orden_activo" y se elimina de donde esté. Por defecto lo tendrá id_order de mayor a menor, es decir, id="orden_id_abajo", de modo que se utilizará como otro parámetro para la llamada a controlador, sacando que flecha esta "marcada"
    const flechas_ordenar = document.querySelectorAll('.filtro_orden');

    // flechas_ordenar.forEach( item => {
    //     item.addEventListener('click', buscarOrdenado); 
    // });

    flechas_ordenar.forEach( item => {
        item.addEventListener('click', function (e) {   
            //si modificamos el orden de los pedidos pedidos por página no estando en la página 1 debemos resetear el número de página, de modo que se lanzará la búsqueda a partir de página 1
            if (document.querySelector('#numero_pagina').innerHTML != 1) {
                document.querySelector('#numero_pagina').innerHTML = 1;
            }  
            //llamamos a buscarOrdenado, que recogerá lo que tengamos en select e inputs.
            buscarOrdenado(e);        
        })
    });

    //añadimos eventlistener a las flechas de paginación (siguiente/anterior página, última/primera página). 
    const flechas_paginacion = document.querySelectorAll('.flechas_paginacion');

    flechas_paginacion.forEach( item => {
        item.addEventListener('click', buscarOrdenado); 
    });

    //añadimos event listener para el input de id_order, para cuando se escriba y se pulse Enter
    document.querySelector('#filtro_id').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            //llamamos a buscarOrdenado, que recogerá lo que hayamos introducido en el input. Esto entra como valor flechaid, pero no importa porque el controlador luego desecha el contenido
            buscarOrdenado(e);
        }
    });

    //añadimos event listener para el select de proveedor, para cuando se cambia
    document.querySelector('#filtro_proveedor').addEventListener('change', function (e) {        
        //llamamos a buscarOrdenado, que recogerá lo que tengamos en select e inputs.
        buscarOrdenado(e);        
    });

    //añadimos event listener para el select de estado, para cuando se cambia
    document.querySelector('#filtro_estado').addEventListener('change', function (e) {        
        //llamamos a buscarOrdenado, que recogerá lo que tengamos en select e inputs. 
        buscarOrdenado(e);        
    });

    //añadimos event listener para el select de paginación, para cuando se cambia
    document.querySelector('#filtro_limite_pedidos').addEventListener('change', function (e) {   
        //si modificamos el número de pedidos por página no estando en la página 1 debemos resetear el número de página, de modo que se lanzará la búsqueda a partir de página 1
        if (document.querySelector('#numero_pagina').innerHTML != 1) {
            document.querySelector('#numero_pagina').innerHTML = 1;
        }  
        //llamamos a buscarOrdenado, que recogerá lo que tengamos en select e inputs.
        buscarOrdenado(e);        
    });

    //generamos el botón para subir hasta arriba haciendo scroll
    const boton_scroll = document.createElement('div');    
    boton_scroll.id = "boton_scroll";
    boton_scroll.innerHTML =  `<i class="icon-arrow-up"></i>`;

    boton_scroll.addEventListener('click', scrollArriba);

    //lo append al panel, y con css lo haremos fixed
    div_pedidos.appendChild(boton_scroll);

    //trás cargar la tabla vacía, filtros etc, obtenemos los pedidos para mostrarlos al cargar la página directamente
    obtenerPedidosDropshipping();

}
//función para subir cuando se pulsa el botón de scroll arriba
function scrollArriba() {
    $('html, body').animate({scrollTop : 0},1000);
    // return false;
}

//función que llena el select de proveedores dropshipping
function obtenerProveedores() {
    var dataObj = {};

    //el token lo hemos sacado arriba del input hidden
    $.ajax({
        url: 'index.php?controller=AdminPedidosDropshipping' + '&token=' + token + "&action=obtener_proveedores" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        
        {
            if (typeof data.error === 'undefined')
            {                
                //recibimos via ajax un array con los datos para el select correspondiente
                // console.dir(data.contenido_select); 
                const contenido_select = data.info_proveedores;       
                
                const select = document.querySelector('#filtro_proveedor');
                
                //vaciamos los select
                select.innerHTML = '';    

                var options_select = '<option value="0" selected> - </option>'; //permitimos un valor nulo, que si es seleccionado no aplica filtro    
                                
                contenido_select.forEach(
                    supplier => {                        
                        options_select += '<option value="'+supplier.id_supplier+'">'+supplier.name+'</option>';
                    }
                );
                
                // console.log(options_select);
                select.innerHTML = options_select;                           
                
            }
            else
            {      
                showErrorMessage(data.message);
            }

        },
        error: function (jqXHR, textStatus, errorThrown)
        {
            showErrorMessage('ERRORS: ' + textStatus);
        }
    });  //fin ajax

}

//función que hace la llamada a obtenerPedidosDropshipping() asignando el filtro de ordenar correspondiente a la flecha pulsada
function buscarOrdenado(e) { 
    //obtenemos el id de la flecha pulsada que define como se ordenará la búsqueda. Si la llamada procede de pulsar el Enter en un input o utilizar un select, no importa ya que en el controlador, si el valor no es flecha tal arriba o abajo, ignora lo que ponga, y el value del input o select se recoge aquí., sacamos el contenido de los inputs y select de abajo si tienen algo y lo enviamos todo como parámetro a la función obtenerPedidosDropshipping() que hará la llamada ajax
    //30/08/2022 Para "recordar" la flecha de orden seleccionada si se ha pulsado alguna se le añade al <a> pulsado la clase "orden_activo" y se elimina de donde esté. Por defecto lo tendrá id_order de mayor a menor, es decir, id="orden_id_abajo", de modo que se utilizará como otro parámetro para la llamada a controlador, sacando que flecha esta "marcada". Para esto comprobamos aquí si lo que ha disparado la petición de los pedidos contiene la clase "filtro_orden" que son las flechas de id y fecha. Si la contiene comprobamos la clase "orden_activo", si no es una flecha de orden, no hacemos nada
    //para la paginación, comprobamos si se ha llegado aquí por la pulsación de una flecha de paginación con la clase flechas_paginación. Si es así asignamos a la varable paginacion el id de la flecha pulsada para enviar al controlador, si no  va vacio

    var paginacion = '';

    if (e.currentTarget.classList.contains('filtro_orden')) {
        // console.log('contiene filtro_ordern'); 
        //el elemento es una flecha de orden. Rotamos todos los elementos de clase filtro_orden eliminando la clase "orden_activo" y después le ponemos a este elemento dicha clase
        
        document.querySelectorAll('.filtro_orden').forEach( item => {
            item.classList.remove('orden_activo'); 
        });
        
        //añadimos la clase al elemento actual
        e.currentTarget.classList.add('orden_activo');

    } else if (e.currentTarget.classList.contains('flechas_paginacion')) {
        // console.log('contiene flechas_paginacion='+e.currentTarget.id); 
        //se ha pulsado una flecha de paginación
        paginacion = e.currentTarget.id;
        
    }

    //buscamos que flecha tiene la clase "orden_activo" para enviar el orden como parámetro (en caso de que la llamada a pedidos no se haya realizado con una flecha de orden, así obtenemos el orden que se ha pedido antes o el por defecto)   
    const flecha_orden = document.querySelector('.orden_activo').id;

    const busqueda_id = document.querySelector('#filtro_id').value;    
    const busqueda_proveedor = document.querySelector('#filtro_proveedor').value;  
    const busqueda_estado = document.querySelector('#filtro_estado').value; 
    const busqueda_fecha_desde = document.querySelector('#filtro_desde').value; 
    const busqueda_fecha_hasta = document.querySelector('#filtro_hasta').value; 
    const busqueda_limite_pedidos = document.querySelector('#filtro_limite_pedidos').value; 
    //obtenemos el valor de número de página del paginador
    const numero_pagina = document.querySelector('#numero_pagina').innerHTML; 

    // console.log('flechaid='+flechaId); 
    // console.log(busqueda_id);
    // console.log(busqueda_proveedor);  
    // console.log(busqueda_estado);
    // console.log(busqueda_fecha_desde);
    // console.log(busqueda_fecha_hasta);
    console.log('numero_pagina='+numero_pagina); 
        
    obtenerPedidosDropshipping(busqueda_id, busqueda_proveedor, busqueda_estado, busqueda_fecha_desde, busqueda_fecha_hasta, flecha_orden, busqueda_limite_pedidos, numero_pagina, paginacion);
    
}

function obtenerPedidosDropshipping(id = 0, proveedor = 0, estado = 0, busqueda_fecha_desde = '', busqueda_fecha_hasta = '', orden = '', limite_pedidos_pagina = 20, numero_pagina = 1, flecha_paginacion = '') {
    // console.log('obtenerPedidosDropshipping');  
    //ante cualquier búsqueda, si hay algo en el panel lateral limpiamos    
    if (document.contains(document.querySelector('#div_pedido'))) {
        document.querySelector('#div_pedido').remove();
    } 

    //mostramos spinner
    // Spinner();

    const buscar_id = id;
    const buscar_proveedor = proveedor;
    const buscar_estado = estado;    
    const buscar_pedidos_desde = busqueda_fecha_desde;
    const buscar_pedidos_hasta = busqueda_fecha_hasta;
    const ordenar = orden;
    const limite_pedidos = limite_pedidos_pagina; //por defecto pedimos 20 pedidos por página, salvo que se cambie el select. Valor 0 significa mostrar todos los pedidos
    const pagina_actual = numero_pagina; //enviamos la página actual por si se pide paginar a izquierda o derecha, utilizando como offset. Por defecto 1
    const paginacion = flecha_paginacion; //si se pulsa flecha de paginación aquí llegará el id de la flecha que informa del sentido de página y si es una o hasta el final
    //vamos a hacer una petición ajax a la función ajaxListaPedidos en el controlador AdminPedidosDropshipping que nos devuelva la lista de pedidos de la tabla lafrips_dropshipping 
    var dataObj = {};

    dataObj['buscar_id'] = buscar_id;
    dataObj['buscar_proveedor'] = buscar_proveedor;
    dataObj['buscar_estado'] = buscar_estado;  
    dataObj['buscar_pedidos_desde'] = buscar_pedidos_desde;  
    dataObj['buscar_pedidos_hasta'] = buscar_pedidos_hasta;    
    dataObj['ordenar'] = ordenar;
    dataObj['limite_pedidos'] = limite_pedidos;
    dataObj['pagina_actual'] = pagina_actual;
    dataObj['paginacion'] = paginacion;

    console.dir(dataObj);
    //el token lo hemos sacado arriba del input hidden
    $.ajax({
        url: 'index.php?controller=AdminPedidosDropshipping' + '&token=' + token + "&action=lista_pedidos" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        
        {
            if (typeof data.error === 'undefined')
            {                
                //recibimos via ajax en data.info_pedidos la información de los pedidos
                // console.log('data.info_pedidos = '+data.info_pedidos);
                console.dir(data);                    

                //con los datos, llamamos a la función que nos los mostrará
                muestraListaPedidos(data.info_pedidos, data.total_pedidos, data.pagina_actual); 

                //eliminamos spinner
                // if (document.contains(document.querySelector('#spinner'))) {
                //     document.querySelector('#spinner').remove();
                // }

            }
            else
            {                    
                //eliminamos spinner
                // if (document.contains(document.querySelector('#spinner'))) {
                //     document.querySelector('#spinner').remove();
                // }

                //limpiamos tabla
                if (document.contains(document.querySelector('#tbody'))) {
                    document.querySelector('#tbody').remove();
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

function muestraListaPedidos(pedidos, total_pedidos, pagina_actual) {
    //primero limpiamos el tbody
    if (document.contains(document.querySelector('#tbody'))) {
        document.querySelector('#tbody').remove();
    } 
    //generamos el tbody con los pedidos obtenidos, que insertaremos tras el thead
    const tbody = document.createElement('tbody');
    tbody.id = 'tbody';
    var num_pedidos = 0;
    //por cada pedido, generamos un tr que hacemos appenchild a tbody
    pedidos.forEach(
        pedido => {
            num_pedidos++;     
            var estado = '';
            var envio = '';     
            var badge_estado = '';           

            if (pedido.error == 1) {                     
                estado = 'Error';
                badge_estado = 'danger';
            } else if (pedido.cancelado == 1) { 
                estado = 'Cancelado';
                badge_estado = 'danger';
            } else if ((pedido.procesado == 1) && (pedido.finalizado == 0)) { 
                estado = 'Aceptado';
                badge_estado = 'info';
            }  else if ((pedido.procesado == 1) && (pedido.finalizado == 1)) { 
                estado = 'Finalizado';
                badge_estado = 'success';
            } else {
                estado = 'Pendiente';
                badge_estado = 'warning';
            }

            if (pedido.envio_almacen == 1) {
                envio = 'Almacén';
            } else {
                envio = 'Cliente';
            }                

            var tr_pedido = document.createElement('tr');
            tr_pedido.id = 'tr_'+pedido.id_order;
            tr_pedido.innerHTML = `
                <td class="row-selector text-center">
                    <input class="noborder checks_linea_pedido" type="checkbox" name="orderBox[]" value="${pedido.id_dropshipping}">
                </td>
                <td class="fixed-width-sm center">
                    ${pedido.id_order} 
                </td>
                <td class="fixed-width-xl center">
                    ${pedido.referencia_pedido}
                </td>
                <td class="fixed-width-xl center">
                    ${pedido.supplier_name} 
                </td>
                <td class="fixed-width-xl center">
                    <span class="badge badge-${badge_estado}">${estado}</span>                        
                </td>
                <td class="fixed-width-xl center">
                    ${envio}
                </td>
                <td class="fixed-width-sm center">
                    ${pedido.date_add} 
                </td>                    
                <td class="text-right"> 
                    <div class="btn-group pull-right">
                        <a href="${pedido.url_pedido}" target="_blank" class="btn btn-default" title="Ir a pedido">
                            <i class="icon-wrench"></i> Ir
                        </a>    
                    </div> 
                </td>
                <td class="text-right"> 
                    <div class="btn-group pull-right">
                        <button class="btn btn-default ver_pedido" type="button" title="Ver pedido" id="ver_${pedido.id_dropshipping}" name="ver_${pedido.id_dropshipping}">
                            <i class="icon-search-plus"></i> Ver
                        </button>    
                    </div>           
                </td>
            `;

            tbody.appendChild(tr_pedido);

        }     
    ) 

    //añadimos al texto de panel-heading el número de pedidos totales que corresponden a los filtros, independientemente de los que se muestran por la paginación   
    document.querySelector('.panel-heading').innerHTML = '<i class="icon-pencil"></i> PEDIDOS DROPSHIPPING - ' + total_pedidos;    

    document.querySelector('#total_pedidos').innerHTML = total_pedidos;

    //ponemos el número de página en que estamos en el paginador
    document.querySelector('#numero_pagina').innerHTML = pagina_actual;

    //en función de los datos obtenidos, la página actual y la posibilidad de mostrar otras páginas, añadimos o quitamos la clase "deshabilita_paginador" a las flechas de paginación que toque. Pej, si estamos en primera página no podemos pulsar a izquierda, pero si además no hubiera más que una página tampoco podríamos a la derecha. Primero obtenemos el límite por página.
    var limite_pagina = document.querySelector('#filtro_limite_pedidos').value; 
    // console.log('limite_pagina='+limite_pagina);
    // console.log('total_pedidos='+total_pedidos);
    // console.log('pagina_actual='+pagina_actual);
    
    //por alguna razón tengo que forzar con parseInt() ya que si limite_pagina es 100 no valida la comparación
    if (limite_pagina == 0 || (parseInt(limite_pagina) > parseInt(total_pedidos))) {
        // console.log('if 1');
        //si límite es 0 es que se muestran todos, no debe pulsarse ninguna flecha. Tampoco si limite es mayor que el total de pedidos
        document.querySelectorAll('.flechas_paginacion').forEach( item => {
            if (!item.classList.contains('deshabilita_paginador')) {
                item.classList.add('deshabilita_paginador'); 
            }
        });
    } else if (pagina_actual == 1) {
        // console.log('if 2');
        //si estamos en la primera página no se debe pulsar izquierda, y nos aseguramos de que se pueda derecha
        if (!document.querySelector('#pagination_left_left').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_left_left').classList.add('deshabilita_paginador'); 
        }
        if (!document.querySelector('#pagination_left').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_left').classList.add('deshabilita_paginador'); 
        }

        if (document.querySelector('#pagination_right_right').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_right_right').classList.remove('deshabilita_paginador'); 
        }
        if (document.querySelector('#pagination_right').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_right').classList.remove('deshabilita_paginador'); 
        }     
        
    } else if (pagina_actual == Math.ceil(total_pedidos/limite_pagina)) {
        // console.log('if 3');
        //si la página actual es la última, no se debe pulsar derecha y aseguramos que se pueda izquierda
        //la página final se calcula dividiendo el total de pedidos entre el límite redondeando arriba
        // 27/10= 2.7 => 3 Usamos Math.ceil(total_pedidos/limite_pagina)
        if (document.querySelector('#pagination_left_left').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_left_left').classList.remove('deshabilita_paginador'); 
        }
        if (document.querySelector('#pagination_left').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_left').classList.remove('deshabilita_paginador'); 
        }

        if (!document.querySelector('#pagination_right_right').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_right_right').classList.add('deshabilita_paginador'); 
        }
        if (!document.querySelector('#pagination_right').classList.contains('deshabilita_paginador')) {
            document.querySelector('#pagination_right').classList.add('deshabilita_paginador'); 
        }    

    } else {
        // console.log('if 4');
        //si no se da ninguno de los casos anteriores permitmos pulsar todas
        document.querySelectorAll('.flechas_paginacion').forEach( item => {
            if (item.classList.contains('deshabilita_paginador')) {
                item.classList.remove('deshabilita_paginador'); 
            }
        });
    }

    
    //ponemos tbody en la tabla
    document.querySelector('#tabla').appendChild(tbody);

    //añadimos event listener para cada botón de Ver, que llamará a la función para mostrar el contenido del pedido Dropshipping
    const botones_ver_pedido = document.querySelectorAll('.ver_pedido');

    botones_ver_pedido.forEach( item => {
        item.addEventListener('click', mostrarPedido);             
    });
    
    //añadimos eventlistener a cada check de producto, si se marca se hará enabled el input de propuesta de compra, y sino se hara disabled   
    // document.querySelectorAll('.checks_linea_producto').forEach( item => {
    //     item.addEventListener('change', enableInputPropuesta); 
    // });
} 

//función que trae id_order y supplier para buscar los datos del pedido y mostrarlos en el lateral
function mostrarPedido(e) {
    console.log('mostrar pedido');
    //primero limpiamos el div id div_pedidos
    if (document.contains(document.querySelector('#div_pedido'))) {
        document.querySelector('#div_pedido').remove();
    } 

    //usamos currentTarget en lugar de target, ya que si se pulsa sobre el icono del botón lo interpreta como target, no teniendo la clase que buscamos, ni el id, etc. Con currentTarget, se va hacia arriba buscando el disparador del event listener
    if(e.currentTarget && e.currentTarget.classList.contains('ver_pedido')){                    
        //para sacar el id_dropshipping, cogemos el id del botón pulsado y separamos por _        
        var botonId = e.currentTarget.id;
        var splitBotonId = botonId.split('_');
        var id_dropshipping = splitBotonId[splitBotonId.length - 1];     
        
        console.log(id_dropshipping);       
        
        // //mostramos spinner
        // Spinner();

        var dataObj = {};
        dataObj['id_dropshipping'] = id_dropshipping;
        //el token lo hemos sacado arriba del input hidden
        $.ajax({
            url: 'index.php?controller=AdminPedidosDropshipping' + '&token=' + token + "&action=ver_pedido" + '&ajax=1' + '&rand=' + new Date().getTime(),
            type: 'POST',
            data: dataObj,
            cache: false,
            dataType: 'json',
            success: function (data, textStatus, jqXHR)
            
            {
                if (typeof data.error === 'undefined')
                {                                 
                    console.dir(data.info_pedido);     
                    var id_supplier = data.id_supplier;
                    
                    //según el proveedor mostraremos los datos de una forma u otra¿? A 23/03/2022 solo tenemos Disfrazzes id 161
                    //07/06/2022 añadimos Globomatik
                    //10/06/2022 Los queno tengan todavía gestión de api los mostramos de forma genérica
                    switch(id_supplier) {
                        case '161':
                            //Disfrazzes
                            muestraPedidoDisfrazzes(data.info_pedido);

                            break;
                        case '160':
                            //DMI 
                            muestraPedidoDmi(data.info_pedido);

                            break;
                        case '156':
                            //Globomatik
                            muestraPedidoGlobomatik(data.info_pedido);
                        
                            break;
                        case '159':
                            //Mars Gaming sin gestión
                            muestraPedidoProveedorSinGestion(data.info_pedido);
                            
                            break;
                        case '163':
                            //Printful sin gestión
                            muestraPedidoProveedorSinGestion(data.info_pedido);
                            
                            break;

                        default:
                           
                    }          

                    //eliminamos spinner
                    // if (document.contains(document.querySelector('#spinner'))) {
                    //     document.querySelector('#spinner').remove();
                    // }

                }
                else
                {                    
                    //eliminamos spinner
                    // if (document.contains(document.querySelector('#spinner'))) {
                    //     document.querySelector('#spinner').remove();
                    // }

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

//función para mostrar los datos de pedido Disfrazzes
function muestraPedidoDisfrazzes(info) {
    // console.log('muestraPedidoDisfrazzes');    

    const div_pedido = document.createElement('div');
    div_pedido.classList.add('clearfix','panel_sticky');
    div_pedido.id = 'div_pedido';
    document.querySelector('div#div_pedidos').appendChild(div_pedido);

    var pedido_response_msg = info.dropshipping.response_msg;
    if (pedido_response_msg == '') {
        pedido_response_msg = 'Pedido sin solicitar / error en petición';
    }

    var pedido_date_expedicion = info.dropshipping.date_expedicion;
    if (pedido_date_expedicion == '0000-00-00') {
        pedido_date_expedicion = 'Pendiente';
    }

    var pedido_tracking = info.dropshipping.tracking;
    if (pedido_tracking == '') {
        pedido_tracking = 'Pendiente de envío';
    } else {
        pedido_tracking = pedido_tracking+'<br>'+info.dropshipping.url_tracking;        
    }

    if (info.dropshipping.response_result != 1) {
        var mensaje_api = `
        <div class="col-lg-9">
        <div class="alert alert-danger clearfix">          
            <div class="col-lg-10">                
            <b>Mensaje API:</b><br> <span title="API result: ${info.dropshipping.response_result}">${pedido_response_msg}</span>  
            </div>                       
        </div>
        </div>
        `;
    } else {
        //el pedido ha entrado pero si su status_id es 0 aún no se ha solictado estado, si es 10 ha sido anulado, si es 4 está enviado, y el resto cuentan como pendiente o procesando        
        if (info.dropshipping.status_id == 10 || info.dropshipping.error == 1) {
            //anulado 
            var badge = 'danger';
        } else if (info.dropshipping.status_id == 4) {
            //enviado 
            var badge = 'success';
        } else {
            //pendiente
            var badge = 'warning';
        }

        var mensaje_api = `
        <div class="col-lg-12"> 
        <div class="alert alert-${badge} clearfix">         
            <div class="col-lg-2">                
            <b>Mensaje API / Estado:</b><br> 
                <span title="API result: ${info.dropshipping.response_result}">
                ${pedido_response_msg} / ${info.dropshipping.status_name}
                </span> 
            </div>
            <div class="col-lg-2">                
            <b>Ref. Disfrazzes / ID:</b><br> ${info.dropshipping.disfrazzes_reference} / ${info.dropshipping.disfrazzes_id}
            </div> 
            <div class="col-lg-1">                
            <b>Entrega:</b><br> ${info.dropshipping.response_delivery_date}  
            </div>
            <div class="col-lg-1">                
            <b>Expedición:</b><br> <span title="${info.dropshipping.date_expedicion}">
                                        ${pedido_date_expedicion}
                                    </span>
            </div>
            <div class="col-lg-6">                
            <b>Seguimiento:</b> ${pedido_tracking}
            </div>          
        </div>
        </div>
        `;
    }

    //sacamos los productos, si han llegado
    if (info.productos) {
        var productos = `
        <div class="table-responsive">
        <table class="table" id="productos_dropshipping_${info.dropshipping.id_supplier}_${info.dropshipping.id_order}">
            <thead>
            <tr>
                <th></th>
                <th><span class="title_box">Producto</span></th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Prestashop</small>
                </th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Proveedor</small>
                </th>
                <th>
                <span class="title_box">Cantidad<br>Solicitada</span>              
                </th>
                <th>
                <span class="title_box">Cantidad<br>Aceptada</span>              
                </th>
                <th>
                <span class="title_box">API Code</span>              
                </th>
                <th>
                <span class="title_box">Mensaje API</span>              
                </th>		
                <th></th>
            </tr>
            </thead>
            <tbody>
        `;

        info.productos.forEach(
            producto => {
                if (producto.variant_msg == '') {
                    var variant_msg = 'Sin info';
                } else {
                    var variant_msg = producto.variant_msg;
                }
                var prod = `
                <tr>
                <td><img src="https://${producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px" title="${producto.id_product}"></td>
                <td>${producto.product_name}</td>
                <td>${producto.product_reference}</td>
                <td>${producto.product_supplier_reference}</td>
                <td class="text-center">${producto.product_quantity}</td>
                <td class="text-center">${producto.variant_quantity_accepted}</td>
                <td class="text-center">${producto.variant_result}</td>
                <td>${variant_msg}</td>                
                </tr>
                `;

                productos += prod;

            }
        );

        productos += `
                </tbody>
            </table>
        </div>
        `;

    } else {
        var productos = `
        <div class="alert alert-danger">          
            No encontrados productos                       
        </div>`;
    }

    //10/01/2023 Añadimos un botón para solicitar el estado del pedido con la API
    //si badge no es 'warning' es que o está enviado o anulado y no se permite pulsar botón
    if (badge !== "warning") {
        var disabled = "disabled";
    } else {
        var disabled = "";
    }

    var boton_estado = `        
        <div class="btn-group pull-right">
            <button type="button" name="estado_${info.dropshipping.id_dropshipping}"  id="estado_${info.dropshipping.id_dropshipping}" class="btn btn-info" title="Estado pedido" ${disabled}>
                <i class="icon-truck"></i>
                Estado
            </button>                  
        </div>        
    `;       

    div_pedido.innerHTML = `
        <div class="panel">                        
            <h3>${info.dropshipping.supplier_name} - ${info.dropshipping.id_order} - ${info.dropshipping.estado_prestashop} ${boton_estado}</h3> 
            <div class="row">
                ${mensaje_api}        
            </div>
            <div class="row">
                ${productos}         
            </div>
        </div>
    `;

    //asignamos evento al botón de solictar estado y si pulsado llamamos a función solicitarEstado()
    const solicitar_estado = document.querySelector("#estado_"+info.dropshipping.id_dropshipping);

    solicitar_estado.addEventListener('click', solicitarEstado);  
}

//función para mostrar los datos de pedido Globomatik
function muestraPedidoGlobomatik(info) {
    // console.log('muestraPedidoGlobomatik');    

    const div_pedido = document.createElement('div');
    div_pedido.classList.add('clearfix','panel_sticky');
    div_pedido.id = 'div_pedido';
    document.querySelector('div#div_pedidos').appendChild(div_pedido);

    var mensaje_peticion = '';
    var status_id = info.dropshipping.status_id;
    var status_txt = info.dropshipping.status_txt;
    var globomatik_order_reference = info.dropshipping.globomatik_order_reference;
    if (globomatik_order_reference == '') {
        //si no hay id de pedido globomatik es que no se ha pedido aún o hubo error y no lo consiguió
        mensaje_peticion = 'Pedido sin solicitar / error en petición';
    } else if (status_txt == '') {
        //si no hay status_txt pero si id de pedido es que no se ha consultado el estado aún
        mensaje_peticion = 'Pedido solicitado<br>Consulta su estado desde la ficha de pedido';
    } else {
        mensaje_peticion = 'Pedido solicitado<br>'+status_txt;
    }

    var pedido_tracking = info.dropshipping.url_tracking;
    if (pedido_tracking == '') {
        pedido_tracking = 'Pendiente de envío';
    } else {
        pedido_tracking = info.dropshipping.tracking+'<br>'+pedido_tracking;        
    }

    if (info.dropshipping.error == 1 || info.dropshipping.status_id < 0 || globomatik_order_reference == '') {
        //error o no encontrado 
        var badge = 'danger';
    } else if (pedido_tracking == 'Pendiente de envío') {
        //pendiente 
        var badge = 'warning';
    } else {
        //enviado
        var badge = 'success';
    }

    var mensaje_api = `
        <div class="col-lg-12"> 
        <div class="alert alert-${badge} clearfix">         
            <div class="col-lg-4">                
            <b>Mensaje API / Estado:</b><br> 
                ${mensaje_peticion}                 
            </div>
            <div class="col-lg-2">                
            <b>Ref. Globomatik:</b><br> ${globomatik_order_reference}
            </div>             
            <div class="col-lg-6">                
            <b>Seguimiento:</b> ${pedido_tracking}
            </div>          
        </div>
    </div>
    `;    

    //sacamos los productos, si han llegado
    if (info.productos) {
        var productos = `
        <div class="table-responsive">
        <table class="table" id="productos_dropshipping_${info.dropshipping.id_supplier}_${info.dropshipping.id_order}">
            <thead>
            <tr>
                <th></th>
                <th><span class="title_box">Producto</span></th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Prestashop</small>
                </th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Proveedor</small>
                </th>
                <th>
                <span class="title_box text-center">Cantidad<br>Solicitada</span>              
                </th>
                <th>
                <span class="title_box text-center">Canon</span>              
                </th>
                <th>
                <span class="title_box text-center">Precio</span>              
                </th>                		
                <th></th>
            </tr>
            </thead>
            <tbody>
        `;

        info.productos.forEach(
            producto => {                
                var prod = `
                <tr>
                <td><img src="https://${producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px" title="${producto.id_product}"></td>
                <td>${producto.product_name}</td>
                <td>${producto.product_reference}</td>
                <td>${producto.product_supplier_reference}</td>
                <td class="text-center">${producto.product_quantity}</td>
                <td class="text-center">${producto.canon}</td>
                <td class="text-center">${producto.price}</td>                               
                </tr>
                `;

                productos += prod;

            }
        );

        productos += `
                </tbody>
            </table>
        </div>
        `;

    } else {
        var productos = `
        <div class="alert alert-danger">          
            No encontrados productos                       
        </div>`;
    }

    //10/01/2023 Añadimos un botón para solicitar el estado del pedido con la API
    //si badge no es 'warning' es que o está enviado o anulado y no se permite pulsar botón
    if (badge !== "warning") {
        var disabled = "disabled";
    } else {
        var disabled = "";
    }

    var boton_estado = `        
        <div class="btn-group pull-right">
            <button type="button" name="estado_${info.dropshipping.id_dropshipping}"  id="estado_${info.dropshipping.id_dropshipping}" class="btn btn-info" title="Estado pedido" ${disabled}>
                <i class="icon-truck"></i>
                Estado
            </button>                  
        </div>        
    `;     

    div_pedido.innerHTML = `
        <div class="panel">                        
            <h3>${info.dropshipping.supplier_name} - ${info.dropshipping.id_order} - ${info.dropshipping.estado_prestashop} ${boton_estado}</h3> 
            <div class="row">
                ${mensaje_api}        
            </div>
            <div class="row">
                ${productos}         
            </div>
        </div>
    `;

    //asignamos evento al botón de solictar estado y si pulsado llamamos a función solicitarEstado()
    const solicitar_estado = document.querySelector("#estado_"+info.dropshipping.id_dropshipping);

    solicitar_estado.addEventListener('click', solicitarEstado);
}

//función para mostrar los datos de pedido Dmi
function muestraPedidoDmi(info) {
    // console.log('muestraPedidoDmi');    

    const div_pedido = document.createElement('div');
    div_pedido.classList.add('clearfix','panel_sticky');
    div_pedido.id = 'div_pedido';
    document.querySelector('div#div_pedidos').appendChild(div_pedido);

    var mensaje_peticion = '';    
    var estado = info.dropshipping.estado;
    var url_tracking = info.dropshipping.url_tracking;
    var ws_respuesta = info.dropshipping.ws_respuesta;
    if (ws_respuesta == '') {
        //si no hay respuesta de webservice es que no se ha pedido aún o hubo error y no lo consiguió
        mensaje_peticion = 'Pedido sin solicitar / error en petición';
    } else if (estado == '') {
        //si no hay estado pero si ws_respuesta sería que no se ha consultado estado
        mensaje_peticion = 'Pedido solicitado<br>Consulta su estado desde la ficha de pedido';
    } else if (url_tracking != '') {
        //pedido enviado
        mensaje_peticion = 'Pedido enviado<br>'+estado;
    } else {
        mensaje_peticion = 'Pedido solicitado<br>'+estado;
    }

    var pedido_factura = info.dropshipping.num_factura;
    if (pedido_factura == '') {
        pedido_factura = 'Pendiente';
    } else {
        pedido_factura = pedido_factura+'<br>'+info.dropshipping.fecha_factura;
    }

    var pedido_tracking = info.dropshipping.url_tracking;
    if (pedido_tracking == '') {
        pedido_tracking = '<br>Pendiente de envío';
    } else {
        pedido_tracking = pedido_tracking+'<br>'+info.dropshipping.transportista;        
    }

    var expedicion_dmi = info.dropshipping.expedicion_dmi;
    if (expedicion_dmi == '') {
        expedicion_dmi = 'Pendiente';
    }

    if (info.dropshipping.error == 1 || ws_respuesta == '') {
        //error o no encontrado 
        var badge = 'danger';
        ws_respuesta = 'Pedido sin solicitar / error en petición';
    } else if (url_tracking == '') {
        //pendiente 
        var badge = 'warning';
    } else {
        //enviado
        var badge = 'success';
    }

    var mensaje_api = `
        <div class="col-lg-12"> 
        <div class="alert alert-${badge} clearfix">         
            <div class="col-lg-3">                
            <b>Mensaje API / Estado:</b><br> 
                ${mensaje_peticion}                 
            </div>
            <div class="col-lg-2">                
                <b>Ref. Dmi:</b><br> ${ws_respuesta}
            </div>
            <div class="col-lg-1">                
                <b>Factura:</b><br> ${pedido_factura}  
            </div>
            <div class="col-lg-1">                
                <b>Expedición:</b><br> ${expedicion_dmi}
            </div>
            <div class="col-lg-5">                
                <b>Seguimiento:</b> ${pedido_tracking}
            </div>          
        </div>
    </div>
    `;    

    //sacamos los productos, si han llegado
    if (info.productos) {
        var productos = `
        <div class="table-responsive">
        <table class="table" id="productos_dropshipping_${info.dropshipping.id_supplier}_${info.dropshipping.id_order}">
            <thead>
            <tr>
                <th></th>
                <th><span class="title_box">Producto</span></th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Prestashop</small>
                </th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Proveedor</small>
                </th>
                <th>
                <span class="title_box text-center">Cantidad<br>Solicitada</span>              
                </th>                
                <th>
                <span class="title_box text-center">Precio</span>              
                </th>                		
                <th></th>
            </tr>
            </thead>
            <tbody>
        `;

        info.productos.forEach(
            producto => {                
                var prod = `
                <tr>
                <td><img src="https://${producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px" title="${producto.id_product}"></td>
                <td>${producto.product_name}</td>
                <td>${producto.product_reference}</td>
                <td>${producto.product_supplier_reference}</td>
                <td class="text-center">${producto.product_quantity}</td>                
                <td class="text-center">${producto.price}</td>                               
                </tr>
                `;

                productos += prod;

            }
        );

        productos += `
                </tbody>
            </table>
        </div>
        `;

    } else {
        var productos = `
        <div class="alert alert-danger">          
            No encontrados productos                       
        </div>`;
    }

    //10/01/2023 Añadimos un botón para solicitar el estado del pedido con la API
    //si badge no es 'warning' es que o está enviado o anulado y no se permite pulsar botón
    if (badge !== "warning") {
        var disabled = "disabled";
    } else {
        var disabled = "";
    }

    var boton_estado = `        
        <div class="btn-group pull-right">
            <button type="button" name="estado_${info.dropshipping.id_dropshipping}"  id="estado_${info.dropshipping.id_dropshipping}" class="btn btn-info" title="Estado pedido" ${disabled}>
                <i class="icon-truck"></i>
                Estado
            </button>                  
        </div>        
    `;     

    div_pedido.innerHTML = `
        <div class="panel">                        
            <h3>${info.dropshipping.supplier_name} - ${info.dropshipping.id_order} - ${info.dropshipping.estado_prestashop} ${boton_estado}</h3> 
            <div class="row">
                ${mensaje_api}        
            </div>
            <div class="row">
                ${productos}         
            </div>
        </div>
    `;

    //asignamos evento al botón de solictar estado y si pulsado llamamos a función solicitarEstado()
    const solicitar_estado = document.querySelector("#estado_"+info.dropshipping.id_dropshipping);

    solicitar_estado.addEventListener('click', solicitarEstado);
}

//función para mostrar los datos de pedidos de proveedores que no tienen gestión API. 
//05/09/2022 Mostraremos un mensaje en función  de si ha sido marcado como finalizado o cancelado manualmente, o si no se ha tocado aún
function muestraPedidoProveedorSinGestion(info) {
    // console.log('muestraPedidoGlobomatik');    

    const div_pedido = document.createElement('div');
    div_pedido.classList.add('clearfix','panel_sticky');
    div_pedido.id = 'div_pedido';
    document.querySelector('div#div_pedidos').appendChild(div_pedido);

    var mensaje_empleado = '';
    var color_mensaje = '';

    if (info.dropshipping.error) {
        color_mensaje = 'alert-danger';        

    } else if (info.dropshipping.finalizado) {
        color_mensaje = 'alert-success';
        mensaje_empleado = '<br>'+info.dropshipping.empleado+' - '+info.dropshipping.fecha_gestion;

    } else if (info.dropshipping.cancelado) {
        color_mensaje = 'alert-danger';
        mensaje_empleado = '<br>'+info.dropshipping.empleado+' - '+info.dropshipping.fecha_gestion;
        
    } else {
        color_mensaje = 'alert-warning';
    }

    var mensaje_api = `
        <div class="col-lg-9">
        <div class="alert ${color_mensaje} clearfix">          
            <div class="col-lg-10">                
            <b>Mensaje API:</b><br> 
            Proveedor y pedido sin gestión vía API <br>
            ${info.dropshipping.mensaje_estado}
            ${mensaje_empleado}
            </div>                       
        </div>
        </div>
        `;

    //sacamos los productos, si han llegado
    if (info.productos) {
        var productos = `
        <div class="table-responsive">
        <table class="table" id="productos_dropshipping_${info.dropshipping.id_supplier}_${info.dropshipping.id_order}">
            <thead>
            <tr>
                <th></th>
                <th><span class="title_box">Producto</span></th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Prestashop</small>
                </th>
                <th>
                <span class="title_box">Referencia</span>
                <small class="text-muted">Proveedor</small>
                </th>
                <th>
                <span class="title_box">Cantidad</span>              
                </th>                	
                <th></th>
            </tr>
            </thead>
            <tbody>
        `;

        info.productos.forEach(
            producto => {                
                var prod = `
                <tr>
                <td><img src="https://${producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px" title="${producto.id_product}"></td>
                <td>${producto.product_name}</td>
                <td>${producto.product_reference}</td>
                <td>${producto.product_supplier_reference}</td>
                <td class="text-center">${producto.product_quantity}</td>                                
                </tr>
                `;

                productos += prod;

            }
        );

        productos += `
                </tbody>
            </table>
        </div>
        `;

    } else {
        var productos = `
        <div class="alert alert-danger">          
            No encontrados productos                       
        </div>`;
    }

    div_pedido.innerHTML = `
        <div class="panel">                        
            <h3>${info.dropshipping.supplier_name} - ${info.dropshipping.id_order} - ${info.dropshipping.estado_prestashop}</h3> 
            <div class="row">
                ${mensaje_api}        
            </div>
            <div class="row">
                ${productos}         
            </div>
        </div>
    `;
}


//función llamada cuando se pulsa un botón de Estado en la vista del pedido. Del id del botón se obtiene el id_dropshipping. Llamamos via ajax a la función para solictar estado que llamará a la clase del proveedor Dropshipping que corresponda y pedimos a la api el estado. Si devuelve correcto disparamos el botón de Ver pedido correspondiente al pedido para que se recargue
function solicitarEstado(e) {
    console.log('solicitar estado'); 
    var botonId = e.currentTarget.id;
    var splitBotonId = botonId.split('_');
    var id_dropshipping = splitBotonId[splitBotonId.length - 1];   
    
    console.log(id_dropshipping); 

    var dataObj = {};
    dataObj['id_dropshipping'] = id_dropshipping;
    //el token lo hemos sacado arriba del input hidden
    $.ajax({
        url: 'index.php?controller=AdminPedidosDropshipping' + '&token=' + token + "&action=estado_pedido" + '&ajax=1' + '&rand=' + new Date().getTime(),
        type: 'POST',
        data: dataObj,
        cache: false,
        dataType: 'json',
        success: function (data, textStatus, jqXHR)
        
        {
            if (typeof data.error === 'undefined')
            {                                 
                console.log("estado solicitado");     
                console.log(data.message); 
                // Seguido hacemos el evento click() sobre el botón Ver del pedido correspondiente para recargar.
                document.querySelector("#ver_"+id_dropshipping).click();    

            }
            else
            {       
                showErrorMessage(data.message);
            }

        },
        error: function (jqXHR, textStatus, errorThrown)
        {
            showErrorMessage('ERRORS: ' + textStatus);
        }
    });  //fin ajax   
   
}