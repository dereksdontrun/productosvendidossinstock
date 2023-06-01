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

{* Plantilla para supplier dropshipping id_supplier 160 - DMI *}


<div class="panel">                        
  <h3>{$proveedor.supplier_name}</h3> 
  <div class="row">
    {if !isset($proveedor.dropshipping)} {* Si no existe array dropshipping dentro del array info para ese proveedor, es que a pesar de ser dropshipping no lo tenemos funcionando *}
      <div class="alert alert-warning">
        <p>
          Proveedor Dropshipping sin gestión
        </p>
      </div>    
    {else}
      <div class="col-lg-9"> 
      {* mostramos el cuadro en rojo si tiene error en lafrips_dropshipping_globomatik, warning mientras no esté finalizado, si status_id es 0 es que aún no se ha solicitado estado o que se acaba de realizar el pedido y están chequeandolo (Pedido en revisión), -1 es no existe, y luego 3 suele ser Facurado, es decir, enviado. Lo comprobamos mejor si existe tracking o no. Aún no sé si hay otros códigos *}
      {if $proveedor.dropshipping.error }
        <div class="alert alert-danger clearfix"> 
          <div class="col-lg-2">                
              <b>Mensaje API / Estado:</b><br> 
                Pedido sin solicitar / error en petición 
            </div>  
      {elseif $proveedor.dropshipping.url_tracking != ''}
        <div class="alert alert-success clearfix">
          <div class="col-lg-2">                
              <b>Mensaje API / Estado:</b><br>                 
                {$proveedor.dropshipping.estado}
            </div>
      {else}   
        <div class="alert alert-warning clearfix">
          <div class="col-lg-2">                
              <b>Mensaje API / Estado:</b><br>
                  {if !$proveedor.dropshipping.estado}Pedido / Estado de pedido sin solicitar{else}{$proveedor.dropshipping.estado}{/if}
                
            </div>
      {/if}   
          
          <div class="col-lg-2">                
            <b>Ref. DMI:</b><br> {if !$proveedor.dropshipping.ws_respuesta}
                                    No disponible
                                  {else}
                                    {$proveedor.dropshipping.ws_respuesta}
                                  {/if}
          </div> 
          <div class="col-lg-1">                
            <b>Factura:</b> {if $proveedor.dropshipping.num_factura == ''} 
                                  <br>Pendiente 
                                {else}
                                  {$proveedor.dropshipping.num_factura }
                                  <br>
                                  {$proveedor.dropshipping.fecha_factura }                              
                                {/if}
          </div>
          <div class="col-lg-2">                
            <b>Expedición:</b> {if $proveedor.dropshipping.expedicion_dmi == ''} 
                                  <br>Pendiente 
                                {else}                                  
                                  <br>
                                  {$proveedor.dropshipping.expedicion_dmi }                              
                                {/if}
          </div>
          <div class="col-lg-5">                
            <b>Seguimiento:</b> {if $proveedor.dropshipping.url_tracking == ''} 
                                  <br>Pendiente de envío 
                                {else}
                                  {$proveedor.dropshipping.transportista }
                                  <br>
                                  {$proveedor.dropshipping.url_tracking }                              
                                {/if}
          </div>      
        </div>
      </div>      
    {/if}
    {if isset($proveedor.dropshipping)}
      <div class="col-lg-3">  
        <form method="post">      
          <div class="row">           
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitSolicitarDropshipping" value="{$proveedor.id_supplier}" id="solicitar_dropshipping_{$proveedor.id_supplier}" class="btn btn-success dropshipping-button" {if $proveedor.finalizado || $proveedor.procesado || $proveedor.cancelado} disabled {/if}>
                  <i class="icon-envelope"></i>
                    Solicitar
                </button>                  
              </div>
            </div>
            <div class="col-lg-6">
              <div class="btn-group">
                <button type="submit" name="submitEstadoDropshipping" value="{$proveedor.id_supplier}" id="estado_dropshipping_{$proveedor.id_supplier}" class="btn btn-info dropshipping-button" {if $proveedor.finalizado || $proveedor.error || $proveedor.cancelado || !$proveedor.procesado} disabled {/if}>
                  <i class="icon-truck"></i>
                    Estado
                </button>                  
              </div>
            </div> 
          </div> 
          <div class="row"> 
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitReactivarDropshipping" value="{$proveedor.id_supplier}" id="reactivar_dropshipping_{$proveedor.id_supplier}" class="btn btn-warning dropshipping-button" {if $proveedor.finalizado || !$proveedor.cancelado || $proveedor.procesado} disabled {/if}>
                  <i class="icon-rotate-left"></i>
                    Reactivar
                </button>                  
              </div>
            </div>  
            <div class="col-lg-6"> 
              <div class="btn-group">
                <button type="submit" name="submitCancelarDropshipping" value="{$proveedor.id_supplier}" id="cancelar_dropshipping_{$proveedor.id_supplier}" class="btn btn-danger dropshipping-button" {if $proveedor.finalizado || $proveedor.procesado || $proveedor.cancelado} disabled {/if}>
                  <i class="icon-trash"></i>
                    Cancelar
                </button>                  
              </div>
            </div>
          </div>
        </form>
      </div>
    {/if}
  </div>
  {if isset($proveedor.dropshipping)}
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
              <th>
                <span class="title_box text-center">Precio</span>              
              </th>              		
              <th></th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$proveedor.productos item=producto}  
              <tr>
                <td><img src="https://{$producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px" title="{$producto.id_product}"></td>
                <td>{$producto.product_name}</td>
                <td>{$producto.product_reference}</td>
                <td>{$producto.product_supplier_reference}</td>
                <td class="text-center">{$producto.product_quantity}</td>                
                <td class="text-center">{$producto.price}</td>                
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
  {/if} <!-- Fin if isset($proveedor.dropshipping)-->
</div>



        