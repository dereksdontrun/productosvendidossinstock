{*
* 2007-2020 PrestaShop
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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{* 16/06/2020
  En esta plantilla mostraremos el producto seleccionado con la lista de proveedores y precio que tiene el producto configurados en Prestashop, y además, si está disponible en la tabla frik_import_catalogos lo mostraremos, con precio y disponibilidad.
  Mostraremos la cantidad de unidades del mismo producto que se encuentran ahora mismo vendidas como producto sin stock (checked = 0 en lafrips_productos_vendidos_sin_stock)
  Tendrá un botón para marcar el producto como revisado, que deberá ejecutar sus funciones desde postProcess() en el controlador AdminProductosVendidosSinStock.
  También? un botón que de la posibilidad de pasar a Revisado todos los productos iguales a este que tengan checked = 0, repasando cada uno de sus pedidos y cambiando o no su estado a Esperando Productos en función de si hay o no algún otro producto vendido sin stock en el mismo pedido
*}
  
  <div class="panel clearfix">
    <h3>Hola {Context::getContext()->employee->firstname} - Producto {if $checked}<span class="badge badge-success">Revisado</span>{/if} Vendido Sin Stock en pedido <a href="{$url_pedido}" target="_blank" title="Ver Pedido" class="link_order">{$id_order}</a> - Estado {$estado_pedido} </h3> 
    {if $checked}<p><span class="badge badge-success">PRODUCTO YA REVISADO - {$nombre_revisador} - <i>{$date_checked}</i></span></p>{/if}   
    {if $out_of_stock != 1}<p><span class="badge badge-pill badge-warning" title="En ocasiones, estas ventas sin stock pueden producirse cuando dos clientes tienen en el carrito el mismo producto, a punto de agotarse, al mismo tiempo">¡ATENCIÓN! Este producto no tenía asignado Permitir Pedidos en el momento de realizarse la compra</span></p>{/if}
    {* <div class="container">   *}
      {if $producto_kids}
        <div class="row">
          <div class="panel col-lg-4 col-md-4 col-sm-4 col-xs-4">                    
            <span class="cerdakids"><span class="badge badge-warning">Producto de Cerdá Automatizado</span></span>    
          </div>
        </div>
      {/if}
      <div class="row">
        <div class="panel col-lg-3 col-md-3 col-sm-3 col-xs-3">                    
          <img src="{$imagen}"  width="218" height="289"/>    
        </div>

        <div class="panel col-lg-3 col-md-3 col-sm-3 col-xs-3">                        
          <h2>{$product_name}</h2>          
          REF: {$product_reference}<br>
          {* Si no tiene ean no lo ponemos *}
          {if ($ean && $ean !== '') }
          EAN: {$ean}<br>
          {/if}          
          <hr> 
          <h4>Proveedor por defecto</h4>
          <b>{$default_supplier}</b> <br>  
          REF: {$product_supplier_reference}<br>
          Coste: {$wholesale_price|string_format:"%.2f"} €<br>
          <hr>
          <h4>Stock disponible de producto</h4>
          {$stock_disponible}<br>
        </div>

        {* Si hay más de un proveedor para el producto lo mostramos *}
        {if $info_proveedores_producto|@count gt 1}
        <div class="panel col-lg-3 col-md-3 col-sm-3 col-xs-4">            
          <h3>Otros proveedores asignados</h3>          
          {foreach $info_proveedores_producto as $proveedor}
            {if $proveedor['id_supplier'] != $id_default_supplier}
              <b>{$proveedor['name']}</b><br>
              REF: {$proveedor['product_supplier_reference']}<br>
              Precio: {$proveedor['product_supplier_price_te']|string_format:"%.2f"} €<br>
              <br>
            {/if}
          {/foreach}            
        </div>
        {/if}

        {* Si se encuentra el producto en la tabla frik_import_catalogos por el ean, y no está en $info_proveedores_producto, lo mostramos *}
        {if $ean && $ean != ''}
          {if $proveedores_import_catalogos|@count gt 0}
          <div class="panel col-lg-3 col-md-3 col-sm-3 col-xs-3">            
            <h3>Proveedores disponibles en Catálogos</h3>
            <br>
            {foreach $proveedores_import_catalogos as $proveedor_import_catalogos}
              {* {if $proveedor['id_supplier'] != $id_default_supplier} *}
                <b>{$proveedor_import_catalogos['nombre_proveedor']}</b><br>
                REF: {$proveedor_import_catalogos['referencia_proveedor']}<br>
                Precio: {$proveedor_import_catalogos['precio']|string_format:"%.2f"} €<br>
                Disponible: {if $proveedor_import_catalogos['disponibilidad']} SI {else} NO {/if}<br>
                <br>
              {* {/if} *}
            {/foreach}            
          </div>
          {/if}
        {/if}
          
      </div>
    {* </div> <!-- container --> *}
  </div>
  <div class="panel clearfix">
    {* <div class="container">  *}
      <div class="row">
      <form action="{$url_base}index.php?controller=AdminProductosVendidosSinStock&token={$token}" method="post">
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 stocks text-center">
          <div class="row">
            <div class="col-lg-8 col-md-8 col-sm-8 col-xs-8 stocks text-center">            
              <h3>Unidades en pedido</h3>
              {$product_quantity}<br>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4 stocks text-center">
              <h3>Total Unidades sin revisar</h3>
              {if $unidades_vendidas_sin_stock}{$unidades_vendidas_sin_stock}{else}0{/if}<br>
            </div>
          </div>
          <div class="row"> 
            <div class="col-lg-8 col-md-8 col-sm-8 col-xs-8 stocks text-center"> 
              <h4>Otros productos sin revisar <br>en pedido</h4>
              {if !$otros_productos_pedidos}
                NO
              {else}
                {foreach $otros_productos_pedidos AS $otro_producto}
                  {$otro_producto['id_product']} - {$otro_producto['product_name']} - {$otro_producto['product_reference']}<br>
                  <span class="link_order">{$otro_producto['product_quantity']}</span> Uds<br>
                  {$otro_producto['supplier']}<br><br>
                {/foreach}
              {/if}
              <br>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4 stocks text-center">
              <h4>Unidades solicitadas Gestor Prepedidos</h4>
              <span class="link_order">{$unidades_gestor_prepedidos}</span> <br>{if $fecha_gestor_prepedidos}{$fecha_gestor_prepedidos}<br>{/if}
              <br>
              <a href="{$url_gestor_prepedidos}" target="_blank" title="Ir a Gestor Prepedidos" class="link_order">Ir a Gestor Prepedidos</a><br>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">

            </div>
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 stocks">
              <div class="form-group">
                <label for="mensaje_pedidos" title="El mensaje será asignado al pedido que contiene el producto actual si pulsas sobre Marcar Revisado o a todos los pedidos que contengan este producto sin revisar si pulsas Marcar Todos como Revisado">Mensaje para pedido/s</label>
                <textarea class="form-control" id="mensaje_pedidos" name="mensaje_pedidos" rows="3" {if $checked && ($unidades_vendidas_sin_stock <= 1)} disabled {else} placeholder="El mensaje será asignado al pedido que contiene el producto actual si pulsas sobre Marcar Revisado o a todos los pedidos que contengan este producto sin revisar si pulsas Marcar Todos como Revisado"{/if}>{if $checked && ($unidades_vendidas_sin_stock <= 1)}Este producto ya ha sido marcado como revisado y no se encuentra sin revisar en más pedidos{/if}</textarea> 
              </div>  
            </div>
            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">

            </div>
          </div>
          <div class="row">
            <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2">

            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4 stocks text-center">
              <label for="submitRevisado_{$id}">Pulsando este botón marcarás como revisada esta unidad de producto y si el pedido no contiene más productos vendidos sin stock sin revisar y se encuentra en estado Pedido Sin Stock Pagado pasará a estado Esperando Productos.</label>
              <button type="submit" id="submitRevisado_{$id}" class="btn btn-success" name="submitRevisado" value="{$id}" {if $checked} disabled{/if}>
                <i class="icon-check"></i> Marcar Revisado
              </button>
            </div>
            <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2">

            </div>
            <div class="col-lg-4 col-md-4 col-sm-4 col-xs-4 stocks text-center">
              <label for="submitTodosRevisados_{$id}">Pulsando este botón marcarás como revisados todos los productos como este, vendidos sin stock y sin revisar, y si sus pedidos se encuentran en estado Pedido Sin Stock Pagado, y no contienen más productos sin revisar, pasarán a estado Esperando Productos.</label>
              <button type="submit" id="submitTodosRevisados_{$id}" class="btn btn-success" name="submitTodosRevisados" value="{$id}" {if ($unidades_vendidas_sin_stock - $product_quantity) < 1} disabled{/if}>
                <i class="icon-check"></i> Marcar Todos como Revisado                
              </button>
            </div>
          </div>
        </div>
      </form>
            
            {* <div class="row">
              <div class="form-group">
                <label for="mensaje_todos_pedidos">Mensaje para TODOS los pedidos</label>
                <textarea class="form-control" id="mensaje_todos_pedidos" name="mensaje_todos_pedidos" rows="3" {if $unidades_vendidas_sin_stock <= 1} disabled{/if}>
                  {if $unidades_vendidas_sin_stock <= 1}No hay otras unidades para marcar como revisadas{/if}
                </textarea>
              </div>            
            </div>        
            <div class="row">
              <div class="col-lg-11 col-md-11 col-sm-11 col-xs-11 stocks text-center">
                <label for="submitTodosRevisados_{$id}">Pulsando este botón marcarás como revisados todos los productos como este, vendidos sin stock y sin revisar, y si sus pedidos se encuentran en estado Pedido Sin Stock Pagado, y no contienen más productos sin revisar, pasarán a estado Esperando Productos.</label>
                <button type="submit" id="submitTodosRevisados_{$id}" class="btn btn-success" name="submitTodosRevisados" value="{$id}" {if $unidades_vendidas_sin_stock <= 1} disabled{/if}>
                  <i class="icon-check"></i> Marcar Todos como Revisado                
                </button>
              </div>
            </div>
          </div>                   
        </div> *}
      
        <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 stocks text-center">
          <div class="row">
            <h3>Ventas Totales</h3>
            {$ventas_totales}<br><br> 
          </div> 
          <div class="row">
            <h4>Ventas últimos 6 meses</h4>
            {$ultimos6meses}<br>
          </div>                  
        </div>        
        <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 stocks text-center">
          <h3>Última Venta</h3>
          {$ultima_venta}<br>                    
        </div>
        <div class="col-lg-2 col-md-2 col-sm-2 col-xs-2 stocks text-center">
          <div class="row">
            <h3>Última Compra</h3>
            {$ultima_compra}<br> 
          </div>  
          <br>
          <br>
          <br>
          <br>
          <div class="row"> 
            {* Botón para volver a panel de Gestión Productos Vendidos Sin Stock *}
            <form action="{$url_base}index.php?controller=AdminProductosVendidosSinStock&token={$token}" method="post">
              <div class="panel-footer">
                <input class="btn btn-lg btn-success center-block" type="submit" id="volver_gestion" name="volver_gestion" value="Volver" />
              </div>
            </form>
          </div>                
        </div>
          
        </div>
      </div>
    {* </div> <!-- container --> *}
  </div>
