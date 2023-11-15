{**
 * Módulo Preoduso Vendidos sin stock
 *}

{* 10/11/2023 metemos un panel donde poder seleccionar un proveedor y pulsando Generar llamaremos al proceso que generará un pedido de materiales *}
{extends file="helpers/list/list_header.tpl"}
{block name='override_header'}    
    <div class="panel col-lg-4 col-md-4 col-sm-4 col-xs-4">
        <form action="{$url_base}index.php?controller=AdminProductosVendidosSinStock&token={$token}" method="post">
            <fieldset style="text-align:center">
                <h3>Pedidos de materiales {if !$info_proveedores}- No hay productos para solicitar{/if}</h3>
                {* <div class="center">&nbsp;</div> *}
                {if $referencia_pedido_existente}
                    Existe un pedido de materiales en estado "Creación en curso" para el proveedor escogido:<br> 
                    <b>{$referencia_pedido_existente}</b><br>
                    Si deseas añadir los productos a procesar a este pedido, pulsa continuar. En caso contrario deberás revisar dicho pedido y modificar su estado para poder generar uno nuevo.<br><br>                    
                    <div class="btn-group">                            
                        <button type="submit" id="submitContinuarPedido" class="btn btn-success" name="submitContinuarPedido" value="{$id_supply_order}">
                            <i class="icon-check"></i> Continuar
                        </button>                                                   
                        <button type="submit" id="submitCancelarPedido" class="btn btn-danger" name="submitCancelarPedido">
                            <i class="icon-stop"></i> Cancelar
                        </button>                            
                    </div>                    
                {else if $info_proveedores}                    
                    <select name="proveedores_solicitar" id="proveedores_solicitar">
                        <option value="0">Selecciona Proveedor</option>
                    {foreach $info_proveedores as $key=>$value}                        
                        <option value="{$key}">{$value}</option>                        
                    {/foreach}               
                    </select>
                    <br>
                    <input type="text" id="referencia_pedido_materiales" name="referencia_pedido_materiales" placeholder="Introduce el nombre a asignar al pedido de materiales" {if $referencia_pedido}value="{$referencia_pedido}"{else}value="{$date_input}"{/if}>
                    <br>
                    <button type="submit" id="submitGenerarPedido" class="btn btn-success" name="submitGenerarPedido">
                        <i class="icon-check"></i> Generar
                    </button>
                {/if}                
            </fieldset>
        </form>
    </div>
    <div class="panel col-lg-4 col-md-4 col-sm-4 col-xs-4">
        Selecciona el proveedor cuyos productos revisados sin asociar a un pedido existente quieres añadir a un pedido de materiales, y la referencia que desees para dicho pedido.<br>
        Se comprobará la existencia de pedidos anteriores para dicho proveedor que se encuentren en estado "Creación en curso".<br>
        Si se encuentra más de un pedido en Creación en curso deberás revisarlos primero. Si existe un solo pedido en Creación en curso, se te dará la opción de añadir los productos a dicho pedido o cambiar su estado y generar uno nuevo. Si no existe ningún pedido en Creación en curso para este proveedor se creará un nuevo pedido de materiales con la referencia introducida.<br>
        Si quieres crear un nuevo pedido de materiales asegurate de que no exista ningún pedido para este proveedor en estado "Creación en curso".
    </div>
{/block}
