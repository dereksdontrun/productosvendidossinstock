/**
 * Creador de pedidos manuales a Cerdá
 *
 *  @author    Sergio™ <sergio@lafrikileria.com>
 *    
 */

//contador para los inputs
var num_input = 1;

$(document).ready(function(){
    // obtenemos token del input hidden que hemos creado con id 'token_admin_modulo_'.$token_admin_modulo, para ello primero buscamos el id de un input cuyo id comienza por token_admin_modulo y al resultado le hacemos substring. 
    //console.log('token input '+$("input[id^='token_admin_modulo']").attr('id'));
    id_hiddeninput = $("input[id^='token_admin_modulo']").attr('id');
    //console.log('token input '+id_hiddeninput);
    //substring, desde 19 hasta final(si no se pone lenght coge el resto de la cadena)
    token = id_hiddeninput.substr(19);
    console.log('token = '+token);

    //vamos generando el formulario dinamicamente utilizando las clases (panel, label etc) de prestashop
    //creamos el div que contiene el formulario, lo añadiremos después del primer form-wrapper, que contiene los input hidden y el select de proveedores
    var form_wrapper = '<div class="form-wrapper" id="wrapper-formulario"></div>';

    // $('.panel-heading').after(form_wrapper);  
    $('.form-wrapper').after(form_wrapper);    

    //generamos el primer input para el primer producto, añadimos un input hidden por cada input cuya función será almacenar un valor que indique si el producto se puede pedir o no. Solo se podrá pedir si existe en prestashop y no está duplicado
    var primer_input = "\
    <div class='form-group' id='form_group_"+num_input+"'>\
        <div class='col-lg-4' id='info_product_"+num_input+"'>\
        </div>\
        <div class='col-lg-6' id='inputs_"+num_input+"'>\
            <div class='row'>\
                <label class='control-label col-lg-4'>\
                    <span class='label-tooltip' data-toggle='tooltip' data-html='true' title='' data-original-title='Introduce la referencia de proveedor del producto'>\
                        Referencia de Proveedor\
                    </span>\
                </label>\
                <div class='col-lg-4'>\
                    <input class='referencia_proveedor' id='supplier_reference_"+num_input+"' type='text' name='supplier_reference_"+num_input+"'  placeholder='Referencia de proveedor'>\
                </div>\
                <input class='referencia_correcta' id='referencia_correcta_"+num_input+"' type='hidden' name='referencia_correcta_"+num_input+"' value='0'>\
                <div class='col-lg-2'>\
                    <button type='button' id='botonbusca_"+num_input+"' name='botonbusca_"+num_input+"' class='btn btn-xs btn-default pull-left busca_referencia'>\
                        <i class='process-icon-search icon-search'></i>\
                    </button>\
                </div>\
            </div>\
            <div class='row'>\
                <label class='control-label col-lg-4'>\
                    <span class='label-tooltip' data-toggle='tooltip' data-html='true' title='' data-original-title='Introduce el número de unidades a solicitar del producto'>\
                        Cantidad\
                    </span>\
                </label>\
                <div class='col-lg-2'>\
                    <input class='unidades' id='unidades_"+num_input+"' type='text' name='unidades_"+num_input+"'  placeholder='Cantidad solicitada'>\
                </div>\
                <div class='row' id='div_boton_eliminador_"+num_input+"'>\
                    <div class='col-lg-2'>\
                        <button type='button' id='eliminar_"+num_input+"' name='eliminar_"+num_input+"' class='btn btn-default boton_eliminador'>\
                            <i class='process-icon-cancel'></i> Eliminar\
                        </button>\
                    </div>\
                </div>\
            </div>\
        </div>\
    </div>\
    "; 

    //lo añadimos al wrapper, con append va al final, dentro.
    $('#wrapper-formulario').append(primer_input);

    
    //generamos el panel footer con un botón para añadir otro producto y un botón para finalizar pedido
    var botones = "\
    <div class='panel-footer'>\
        <button type='submit' value='1' id='crear_pedido' name='crear_pedido' class='btn btn-default pull-right'>\
            <i class='process-icon-save icon-save'></i> Crear Pedido\
        </button>\
        <a href='index.php?controller=AdminPedidosManualesProveedor&amp;token="+token+"' class='btn btn-default' onclick='window.history.back();'>\
			<i class='process-icon-cancel'></i> Cancelar\
		</a>\
        <button type='submit' id='otro_producto' name='otro_producto' class='btn btn-default'>\
            <i class='process-icon-plus icon-plus'></i> Añadir Producto\
        </button>\
    </div>";

    $('#fieldset_0').append(botones);

});


$(function(){
    //evitamos que se envíe el formulario al pulsar Enter ya que es común pulsar al pasar al siguiente producto
    $(window).keydown(function(event){
        if(event.keyCode == 13) {
          event.preventDefault();
          return false;
        }
    });

    
    //cuando se pulsa el botón crear pedido(es decir, se hace submit del formulario con id='configuration_form'), validamos los campos y si es correcto, metemos en el input hidden numero_productos una cadena que contiene el valor numérico de los ids de input para poder sacarlo en el controlador y saber que inputs hay que buscar
    //01/09/2023 Hemos añadido un select para seleccionar el proveedor, ahora Cerdá y Karactermanía, de modo que hay que añadir comprobación de que haya un proveedor seleccionado, y en el caso de ser Cerdá se revisará un regex de la referencia y si es karactermanía otro
    $('#configuration_form').on('submit', function(event){
        event.preventDefault();
        console.log('submit pulsado');
        var error = 0;
        var error_no_inputs = 0;
        var error_cantidad = 0;        
        var error_referencia_correcta = 0;
        var error_referencia_repetida = 0;
        var referencias = [];
        var texto_error = '';

        //comprobamos que haya un proveedor seleccionado
        var id_supplier = $("#id_supplier option:selected").val();
        if (id_supplier == 0) {
            error = 1;
            texto_error += '¡Debes seleccionar un proveedor!\n';
        }

        //comprobamos que no esté intentando crear un pedido sin inputs, porque hayan eliminado los inputs del todo
        if ($('.referencia_proveedor').length < 1){
            error = 1;
            error_no_inputs = 1;
        }

        if (error_no_inputs) {
            texto_error += '¡¿Estás intentando crear un pedido vacío?!\n';
        }

        //validamos que en cantidad haya un número
        //cada input de unidades tiene clase "unidades", los chequeamos uno a uno
        $('.unidades').each(function() {
            if (this.value == '' || ((this.value != '') && !(/^[0-9]+$/.test(this.value)))) {
                error = 1;
                error_cantidad = 1;
            }
        });

        if (error_cantidad) {
            texto_error += '¡Debes rellenar cada campo cantidad con un número!\n';
        }

        //validamos que en referencia proveedor haya una referencia válida de cerdá, pueden ser 10 números, o 10 números, un guión bajo y una serie de caracteres. Para Karactermanía de momento son 5 números
        //cada input de referencia_proveedor tiene clase "referencia_proveedor", los chequeamos uno a uno
        $('.referencia_proveedor').each(function() {
            if (id_supplier == 65) {
                //el regexp indica que debe empezar por 10 digitos del 0 al 9, y se puede o no dar lo que hay entre parentesis (..)?, que sería, si se da, un guión bajo, seguido de 1 a 9 caracteres, números, letras  y guión
                //23/08/2023 arreglo regex de referencia para que admita caracter / 
                // (this.value == '' || ((this.value != '') && !(/^[0-9]{10}(_[0-9a-zA-Z\-]{1,9})?$/.test(this.value))))
                if (this.value == '' || ((this.value != '') && !(/^[0-9]{10}(_[0-9a-zA-Z\/\-]{1,9})?$/.test(this.value)))){ 
                    error = 1;                    
                    texto_error += '¡Debes introducir referencias válidas de Cerdá!\n';
                }
            } else if (id_supplier == 53) {
                //5 cifras para Karactermanía
                if (this.value == '' || ((this.value != '') && !(/^[0-9]{5}$/.test(this.value)))){ 
                    error = 1;
                    texto_error += '¡Debes introducir referencias válidas de Karactermanía!\n';
                }
            } else {
                error = 1;
                texto_error += '¡Error con proveedor!\n';
            }
            

            //comprobamos que no haya ninguna referencia repetida. Metemos cada referencia en un array
            referencias.push(this.value);

        });        

        //comprobamos array con referencias. Creamos un array desde un set basado en 'referencias'. Esto elimina duplicados
        var referencias_unicas = Array.from(new Set(referencias));
        console.log('referencias '+referencias)
        console.log('referencias unicas '+referencias_unicas)
        if (referencias.length != referencias_unicas.length) {
            error = 1;
            error_referencia_repetida = 1;
        }

        if (error_referencia_repetida) {
            texto_error += '¡Has introducido algunas referencias repetidas!\n';
        }


        //hemos creado un input hidden id referencia_correcta_nº y class referencia_correcta cuyo value será 1 si el producto existe en prestashop y no está duplicado. Solo permitimos crear el pedido si todos están así
        $('.referencia_correcta').each(function() {           
            console.log('value referencia_correcta '+this.value);
            if (this.value == 0){ 
                error = 1;
                error_referencia_correcta = 1;
                var input_numero = $(this).attr('id').split("_").pop();
                showErrorMessage('Referencia '+$('#supplier_reference_'+input_numero).val()+' pertenece a un artículo que no se puede añadir al pedido');
            }
        });

        if (error_referencia_correcta) {
            texto_error += '¡Alguna de las referencias no corresponde a un producto correcto y que existe en Prestashop!\n';
        }
       
        if (!error) {
            //si error = 0 seguimos con la ejecución del formulario, poniendo el número del input con referencia (la parte final del id) en el input hidden, como string separados por _ , de modo que en el controlador sabremos cuales son los ids que debemos buscar
            //creamos un array donde ir metiendo el número y después usamos .join() para genrar un string que metemos en el value de numero_productos
            var numero_ids_inputs = [];
            $('.referencia_correcta').each(function() {           
                var input_numero_id = $(this).attr('id').split("_").pop();
                numero_ids_inputs.push(input_numero_id);                    
            });
            var numeros_ids = numero_ids_inputs.join('_');
            $('#numero_productos').val(numeros_ids);
            //alert($('#numero_productos').val());
            //arriba se suspendió la ejecución del event, el event era submit del formulario. Si hubiera ejecutado esto al pulsar el botón (onclick), currentTarget.submit no funcionaría, ya que el botón en si no es un submit
            event.currentTarget.submit();
        } else {
            alert(texto_error);
        }
    });
       

    //si se pulsa botón Añadir producto, añadiremos otro input al formulario:
    $('#otro_producto').on('click', function(e){
        console.log('pulsado Añadir producto');
        e.preventDefault();
        num_input++;

        var nuevo_input = "\
        <div class='form-group' id='form_group_"+num_input+"'>\
            <div class='col-lg-4' id='info_product_"+num_input+"'>\
            </div>\
            <div class='col-lg-6' id='inputs_"+num_input+"'>\
                <div class='row'>\
                    <label class='control-label col-lg-4'>\
                        <span class='label-tooltip' data-toggle='tooltip' data-html='true' title='' data-original-title='Introduce la referencia de proveedor del producto'>\
                            Referencia de Proveedor\
                        </span>\
                    </label>\
                    <div class='col-lg-4'>\
                        <input class='referencia_proveedor' id='supplier_reference_"+num_input+"' type='text' name='supplier_reference_"+num_input+"'  placeholder='Referencia de proveedor'>\
                    </div>\
                    <input class='referencia_correcta' id='referencia_correcta_"+num_input+"' type='hidden' name='referencia_correcta_"+num_input+"' value='0'>\
                    <div class='col-lg-2'>\
                        <button type='button' id='botonbusca_"+num_input+"' name='botonbusca_"+num_input+"' class='btn btn-xs btn-default pull-left busca_referencia'>\
                            <i class='process-icon-search icon-search'></i>\
                        </button>\
                    </div>\
                </div>\
                <div class='row'>\
                    <label class='control-label col-lg-4'>\
                        <span class='label-tooltip' data-toggle='tooltip' data-html='true' title='' data-original-title='Introduce el número de unidades a solicitar del producto'>\
                            Cantidad\
                        </span>\
                    </label>\
                    <div class='col-lg-2'>\
                        <input class='unidades' id='unidades_"+num_input+"' type='text' name='unidades_"+num_input+"'  placeholder='Cantidad solicitada'>\
                    </div>\
                    <div class='row' id='div_boton_eliminador_"+num_input+"'>\
                        <div class='col-lg-2'>\
                            <button type='button' id='eliminar_"+num_input+"' name='eliminar_"+num_input+"' class='btn btn-default boton_eliminador'>\
                                <i class='process-icon-cancel'></i> Eliminar\
                            </button>\
                        </div>\
                    </div>\
                </div>\
            </div>\
        </div>\
        "; 

        //lo añadimos al wrapper, con append va al final, dentro.
        $('#wrapper-formulario').append(nuevo_input);
        
   
    });

    //si se pulsa un botón de clase busca_referencia, se hará la búsqueda. Como estos botones, salvo el primero, se crean dinámicamente cuando ya existe la página, no se puede poner p ej : $('.busca_referencia').on('click', function(e){ porque es como si no existiera para el ¿DOM?, de modo que hay que usar un contenedor donde estén, que existiera al crear la página. En este caso uso el id del contenedor del formulario, wrapper-formulario, que al ser pulsado, pulsado en un 'button', pasamos a la función. Dentro de la función comprobamos si el botón tiene la clase busca_referencia o boton_eliminador para saber que botón ha sido pulsado
    $('#wrapper-formulario').on('click', 'button', function(e){
        e.preventDefault();
        if ($(this).hasClass('busca_referencia')) {
            //el botón pulsado es clase busca referencia

            //01/09/2023 Hemos añadido select con proveedores a elegir, primero comprobamos que haya uno seleccionado
            //comprobamos que haya un proveedor seleccionado
            var id_supplier = $("#id_supplier option:selected").val();
            if (id_supplier == 0) {
                alert('¡Debes seleccionar un proveedor!');
                return;
            }
            
            //sacamos número del input (botón) pulsado, queremos lo que haya más a la derecha del _ , no usamos substr porque no sé cuantos caracteres tiene el número:
            var input_num = $(this).attr('id').split("_").pop();
            console.log('pulsado busca referencia = '+$(this).attr('id')+' contenido input = '+$('#supplier_reference_'+input_num).val());

            //hacemos .trim() para evitar espacios en blanco
            var referencia_buscar = $('#supplier_reference_'+input_num).val().trim();
            
            //comprobamos que la referencia introducida existe y es válida para el proveedor
            if (id_supplier == 65) {
                //el regexp indica que debe empezar por 10 digitos del 0 al 9, y se puede o no dar lo que hay entre parentesis (..)?, que sería, si se da, un guión bajo, seguido de 1 a 9 caracteres, números, letras  y guión
                //23/08/2023 arreglo regex de referencia para que admita caracter / 
                // (this.value == '' || ((this.value != '') && !(/^[0-9]{10}(_[0-9a-zA-Z\-]{1,9})?$/.test(this.value))))
                if (referencia_buscar == '' || ((referencia_buscar != '') && !(/^[0-9]{10}(_[0-9a-zA-Z\/\-]{1,9})?$/.test(referencia_buscar)))){ 
                    alert('La referencia no tiene el formato de Cerdá');
                    return;
                }
            } else if (id_supplier == 53) {
                //5 cifras para Karactermanía
                if (referencia_buscar == '' || ((referencia_buscar != '') && !(/^[0-9]{5}$/.test(referencia_buscar)))){ 
                    alert('La referencia no tiene el formato de Karactermanía');
                    return;
                }
            }
            
            //vamos a hacer una petición ajax a la función ajaxBuscaProducto en el controlador AdminPedidosManualesProveedor que nos devuelva si el producto existe en Prestashop, o en su defecto en la tabla frik_catalogo_cerda_crear si es de Cerdá, con algunos datos para mostrar.
            //01/06/2023 Ya no buscamos en catálogo Cerdá
            var dataObj = {};
            dataObj['referencia_buscar'] = referencia_buscar;
            dataObj['id_supplier'] = id_supplier;
            console.log(dataObj);
            console.log('referencia buscar por ajax '+dataObj['referencia_buscar']);

            //el token lo hemos sacado arriba del input hidden
            $.ajax({
                url: 'index.php?controller=AdminPedidosManualesProveedor' + '&token=' + token + "&action=busca_producto" + '&ajax=1' + '&rand=' + new Date().getTime(),
                type: 'POST',
                data: dataObj,
                cache: false,
                dataType: 'json',
                success: function (data, textStatus, jqXHR)
                
                {
                    if (typeof data.error === 'undefined')
                    {
                        //console.log('Producto encontrado');
                        //recibimos via ajax en data.info_producto la información del producto
                        console.log('data.info_producto = '+data.info_producto);
                        console.dir(data.info_producto);

                        
                        if (!data.info_producto['en_prestashop']) {
                            //el producto no está ni en Prestashop , mostramos mensaje 
                            showErrorMessage('Referencia no encontrada en el sistema');
                            //limpiamos el contenido del div de información por si contiene algo
                            $('#info_product_'+input_num).empty();                            

                            var informacion_producto = '<div class="panel color_error"><h3>Referencia introducida no encontrada</h3>\
                            <p>No se encontró ningún producto en Prestashop cuya referencia de proveedor coincida con la introducida.</p>\
                            <p>Comprueba la referencia o elimina este producto.</p>\
                            <p>RECUERDA QUE SI EL PRODUCTO ES UNA TALLA O ATRIBUTO LA REFERENCIA DEBE SER COMPLETA Y DE DICHO ATRIBUTO.</p></div>';

                            $('#info_product_'+input_num).append(informacion_producto);

                           
                        } else if (data.info_producto['multiple_resultado_prestashop']) {
                            //la referencia introducida se encuentra repetida en Prestashop, mensaje
                            showErrorMessage('La Referencia se encuentra repetida en Prestashop');

                            //limpiamos el contenido del div de información por si contiene algo
                            $('#info_product_'+input_num).empty();                            

                            var informacion_producto = '<div class="panel color_error"><h3>Referencia introducida Repetida</h3>\
                            <p>La referencia de proveedor introducida corresponde a más de un artículo de Prestashop.</p>\
                            <p>Esto se debe probablemente a la creación de productos duplicados.</p>\
                            <p>Productos con referencia de proveedor duplicada:</p>';

                            //sacamos cada referencia de producto de prestashop para mostrarlas en el mensaje
                            var referencias_repetidas = '<ul>';

                            Object.entries(data.info_producto['product_supplier']).forEach(([key, value]) => {
                                //en product_supplier están los resultado de la consulta en el controlador, key sería 0, 1 etc y value los resultados reales de la consulta, así que buscamos 'referencia', vale tanto value.referencia como 
                                // value['referencia']
                                console.log(value.referencia);
                                // console.log(value['referencia']);                            
                                referencias_repetidas += '<li>'+value.referencia+'</li>';                                                        
                            });

                            referencias_repetidas += '</ul></div>';
                            informacion_producto += referencias_repetidas;

                            $('#info_product_'+input_num).append(informacion_producto);
                            

                        } else if (data.info_producto['en_prestashop']) {
                            //se ha encontrado la referencia en Prestashop, y no está duplicada. Mostramos el producto
                            showSuccessMessage('Encontrado en Prestashop producto correspondiente a la referencia de proveedor');

                            console.log('id_imagen '+data.info_producto['product_supplier'][0]['id_imagen']);
                            //preparamos imagen
                            if ((!data.info_producto['product_supplier'][0]['id_imagen']) || (data.info_producto['product_supplier'][0]['id_imagen'] == 0)) {
                                url_imagen = 'https://lafrikileria.com/img/logo_producto_medium_default.jpg';
                            } else {
                                url_imagen = data.info_producto['product_supplier'][0]['url_imagen'];
                            }

                            //preparamos mensaje de disponibilidad para compra       
                            //02/10/2023 Ahora, si el producto tiene stock, en principio no tendrá permitir pedido, se mira si tiene disponibilidad en catálogo. Si tiene, se pone color_correcto (verde) a la ficha. Si no tiene disponibilidad se poner color_alerta (naranja). Si no tiene stock ni permitir pedido ni disponibilidad, ponemos alerta, si no tiene stock pero si permitir pedido, se pone correcto, etc
                            var color = "";
                            var mensaje = ""; 
                            var badge = "";                                          

                            if (data.info_producto['product_supplier'][0]['out_of_stock'] == 1) {
                                color = "color_correcto";
                                mensaje = "Producto disponible en catálogo";
                                badge = "badge-success";
                            } else if (data.info_producto['product_supplier']['disponibilidad_catalogos']) {
                                color = "color_alerta";
                                mensaje = data.info_producto['product_supplier']['mensaje_catalogos']; 
                                badge = "badge-warning";                       
                            } else if (!data.info_producto['product_supplier']['disponibilidad_catalogos']) {
                                color = "color_error";
                                mensaje = data.info_producto['product_supplier']['mensaje_catalogos'];    
                                badge = "badge-danger";                            
                            }

                            var disponibilidad = `<p><span class="badge badge-pill ${badge}">${mensaje}</span></p>`;

                            //limpiamos el contenido del div de información por si contiene algo
                            $('#info_product_'+input_num).empty();
                            
                            var informacion_producto = '<div class="panel clearfix '+color+'"><h3>Referencia correspondiente a producto de Prestashop</h3>\
                            <div class="col-lg-4 contenedor_imagen">\
                                <img src="'+url_imagen+'"  width="120" height="160"/>\
                            </div>\
                            <div class="col-lg-8">\
                                <br>\
                                <h4>'+data.info_producto['product_supplier'][0]['nombre']+'</h4>\
                                <p>Ref:  '+data.info_producto['product_supplier'][0]['referencia']+'</p>\
                                <p>Ean13:  '+data.info_producto['product_supplier'][0]['ean13']+'</p>\
                                <p>Stock:  <span style="font-size: 150%">'+data.info_producto['product_supplier'][0]['stock']+'</span></p>\
                                '+disponibilidad+'\
                            </div>\
                            </div>';   

                            $('#info_product_'+input_num).append(informacion_producto);

                            //asignamos value 1 al input hidden id referencia_correcta_numero que indica que el producto existe y es válido hacer pedido
                            $('#referencia_correcta_'+input_num).val(1);

                        //01/06/2023 Ya no buscamos en catálogo Cerdá de modo que no mostramos esto
                        
                        // } else if ((!data.info_producto['en_prestashop']) && (data.info_producto['en_catalogo_cerda'])) {
                        //     //no se ha encontrado la referencia en Prestashop pero si en catalogo de cerdá, mostramos el producto según el catálogo
                        //     showSuccessMessage('Encontrado en catálogo de Cerdá producto correspondiente a la referencia de proveedor');

                        //     //comproobamos si hubiera encontrado resultado múltiple al buscar en catálogo de Cerdá, es un error que no debería darse
                        //     var error_duplicado_cerda = '';
                        //     if (data.info_producto['multiple_resultado_cerda']) {
                        //         showErrorMessage('REFERENCIA DUPLICADA EN CATÁLOGO CERDÁ');
                        //         error_duplicado_cerda = '<h4>REFERENCIA DUPLICADA EN CATÁLOGO CERDÁ</h4>';
                        //     }

                        //     //limpiamos el contenido del div de información por si contiene algo
                        //     $('#info_product_'+input_num).empty();                            

                        //     var informacion_producto = '<div class="panel clearfix color_alerta"><h3>Información Catálogo Cerdá</h3>\
                        //     <div class="row">\
                        //         <div class="col-lg-12">\
                        //             '+error_duplicado_cerda+'\
                        //             <p>El producto correspondiente a la referencia introducida no existe en Prestashop, pero aparece en el catálogo de Cerdá. Recuerda que si es de la gama ADULT debes crear el producto con el importador para poder hacer un pedido de su referencia</p>\
                        //             <p>Si se trata de un producto KIDS que todavía no ha sido creado, comunícaselo al encargado de hacerlo</p>\
                        //         </div>\
                        //     </div>\
                        //     <div class="row">\
                        //         <div class="col-lg-4 contenedor_imagen">\
                        //             <img src="'+data.info_producto['info_catalogo_cerda'][0]['imagen']+'"  width="120" height="160"/>\
                        //         </div>\
                        //         <div class="col-lg-8">\
                        //             <br>\
                        //             <h4>'+data.info_producto['info_catalogo_cerda'][0]['nombre']+'</h4>\
                        //             <p>'+data.info_producto['info_catalogo_cerda'][0]['personaje']+'</p>\
                        //             <p>'+data.info_producto['info_catalogo_cerda'][0]['subfamilia']+' - '+data.info_producto['info_catalogo_cerda'][0]['desc_talla']+'</p>\
                        //             <p>Ean13:  '+data.info_producto['info_catalogo_cerda'][0]['ean']+'</p>\
                        //         </div>\
                        //     </div>\
                        //     </div>';   

                        //     $('#info_product_'+input_num).append(informacion_producto);
                            

                        } else {
                            //si ha llegado hasta aquí pasa algo raruno
                            showErrorMessage('Error');
                        }             

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

        } else if ($(this).hasClass('boton_eliminador')) {
            //el botón pulsado es clase eliminador
            //sacamos número del botón pulsado, queremos lo que haya más a la derecha del _ , no usamos substr porque no sé cuantos caracteres tiene el número:
            var elimina_num = $(this).attr('id').split("_").pop();
            console.log('pulsado boton eliminador = '+$(this).attr('id'));

            //limpiamos el contenido del div correspondiente al botón 
            $('#form_group_'+elimina_num).remove(); 
            
        }
   
    });


    

});




