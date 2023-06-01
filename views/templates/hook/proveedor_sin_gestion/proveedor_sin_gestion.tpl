{*
* 2007-2021 PrestaShop
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
*  @author     PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2021 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{* Plantilla para supplier que es dropshipping pero aún no tiene gestión, mostrará datos genéricos de los productos 
05/09/2022 Modificamos la plantilla cambiando el bnotón de Solictar por un botón Finalizar y permitimos pulsar el de cancelar para poder dar por finalizado o cancelado los pedidos dropshipping que no tienen proceso API y que se quedan en aceptado para siempre. Comprobamos el valor $proveedor.finalizado para ver si ya ha sido pulsado y así desactivar el botón finalizar, mostrar en verde el panel y un mensaje de ok pero sin gestión API.
*}


<div class="panel">                        
  <h3>{$proveedor.supplier_name}</h3> 
  <div class="row">    
    <div class="col-lg-9">
      {if $proveedor.finalizado && !$proveedor.cancelado}
      <div class="alert alert-success clearfix"> 
      {else if !$proveedor.finalizado && $proveedor.cancelado}
      <div class="alert alert-danger clearfix">
      {else}
      <div class="alert alert-warning clearfix">
      {/if}          
        <div class="col-lg-10">                
          <b>Mensaje API:</b><br>            
          Proveedor y pedido sin gestión vía API<br>
          {if $proveedor.finalizado && !$proveedor.cancelado}
            <b>Pedido marcado como Finalizado manualmente</b>
            {if $proveedor.empleado}
              <br>
              {$proveedor.empleado} - {$proveedor.fecha_gestion}
            {/if}
          {else if !$proveedor.finalizado && $proveedor.cancelado}
            <b>Pedido marcado como Cancelado manualmente</b>
            {if $proveedor.empleado}
              <br>
              {$proveedor.empleado} - {$proveedor.fecha_gestion}
            {/if}          
          {else}
            Pulsa Finalizar o Cancelar para marcar el pedido dropshipping una vez gestionado con el proveedor
          {/if}   
        </div>                       
      </div>
    </div>    
    {* Ponemos todos los botones disabled *}
      <div class="col-lg-3">  
        <form method="post">      
          <div class="row">           
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitFinalizarDropshipping" value="{$proveedor.id_supplier}" id="finalizar_dropshipping_{$proveedor.id_supplier}" class="btn btn-success dropshipping-button"
                {if $proveedor.finalizado && !$proveedor.cancelado} disabled {/if}>
                  <i class="icon-flag"></i>
                    Finalizar
                </button>                  
              </div>
            </div>
            <div class="col-lg-6">
              <div class="btn-group">
                <button type="submit" name="submitEstadoDropshipping" value="{$proveedor.id_supplier}" id="estado_dropshipping_{$proveedor.id_supplier}" class="btn btn-info dropshipping-button" disabled>
                  <i class="icon-truck"></i>
                    Estado
                </button>                  
              </div>
            </div> 
          </div> 
          <div class="row"> 
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitReactivarDropshipping" value="{$proveedor.id_supplier}" id="reactivar_dropshipping_{$proveedor.id_supplier}" class="btn btn-warning dropshipping-button" disabled>
                  <i class="icon-rotate-left"></i>
                    Reactivar
                </button>                  
              </div>
            </div>  
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitCancelarDropshipping" value="{$proveedor.id_supplier}" id="cancelar_dropshipping_{$proveedor.id_supplier}" class="btn btn-danger dropshipping-button"
                {if $proveedor.cancelado} disabled {/if}>
                  <i class="icon-trash"></i>
                    Cancelar
                </button>                  
              </div>
            </div>
          </div>
        </form>
      </div>
    
  </div>
  
    <div class="row"> <!-- mostramos los productos con sus mensajes, etc -->
      <div class="table-responsive">
        <table class="table" id="productos_dropshipping_{$proveedor.id_supplier}">
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
              <th></th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$proveedor.productos item=producto}  
              <tr>
                <td><img src="https://{$producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px"></td>
                <td>{$producto.product_name}</td>
                <td>{$producto.product_reference}</td>
                <td>{$producto.product_supplier_reference}</td>
                <td class="text-center">{$producto.product_quantity}</td>                               
                {* <td colspan="2" style="display: none;" class="add_product_fields">&nbsp;</td> *}
                <td class="text-right">
                  {* <div class="btn-group">
                    <button type="button" class="btn btn-default">
                      <i class="icon-trash"></i>
                        Eliminar
                    </button>                  
                  </div>   *}
                </td>
              </tr>
            {/foreach}                                                      
          </tbody>
        </table>
      </div>

    </div> <!-- Fin row productos-->
  
</div>



        