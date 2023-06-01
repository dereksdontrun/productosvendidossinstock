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

{* Plantilla para supplier dropshipping id_supplier 161 - Disfrazzes *}


<div class="panel">                        
  <h3>{$proveedor.supplier_name}</h3> 
  <div class="row">
    {if !isset($proveedor.dropshipping)} {* Si no existe array dropshipping dentro del array infoparaese proveedor, es que a pesar de ser dropshipping no lo tenemos funcionando *}
      <div class="alert alert-warning">
        <p>
          Proveedor Dropshipping sin gestión
        </p>
      </div>
    {elseif $proveedor.dropshipping.response_result != 1}
      <div class="col-lg-9">
        <div class="alert alert-danger clearfix">          
          <div class="col-lg-10">                
            <b>Mensaje API:</b><br> <span title="API result: {$proveedor.dropshipping.response_result}">{if !$proveedor.dropshipping.response_msg}Pedido sin solicitar / error en petición{else}{$proveedor.dropshipping.response_msg}{/if}</span>  
          </div>                       
        </div>
      </div>
    {else}
      <div class="col-lg-9"> 
      {* mostramos el cuadro en rojo si tiene error en lafrips_dropshipping_disfrazzes, warning mientras no esté finalizado, si status_id es 0 es que aún no se ha solicitado estado, 10 es anulado, 4 enviado y el resto, pendiente/preparación *}
      {if $proveedor.dropshipping.status_id == 10 || $proveedor.dropshipping.error }
        <div class="alert alert-danger clearfix">   
      {elseif $proveedor.dropshipping.status_id == 4}
        <div class="alert alert-success clearfix">
      {else}   
        <div class="alert alert-warning clearfix">
      {/if}   
          <div class="col-lg-2">                
            <b>Mensaje API / Estado:</b><br> 
              <span title="API result: {$proveedor.dropshipping.response_result}">
                {$proveedor.dropshipping.response_msg} / {$proveedor.dropshipping.status_name}
              </span> 
          </div>
          <div class="col-lg-2">                
            <b>Ref. Disfrazzes / ID:</b><br> {$proveedor.dropshipping.disfrazzes_reference} / {$proveedor.dropshipping.disfrazzes_id}
          </div> 
          <div class="col-lg-1">                
            <b>Entrega:</b><br> {$proveedor.dropshipping.response_delivery_date|date_format:"%d-%m-%Y"}  
          </div>
          <div class="col-lg-1">                
            <b>Expedición:</b><br> <span title="{$proveedor.dropshipping.date_expedicion}">
                                      {if $proveedor.dropshipping.date_expedicion == '0000-00-00'} 
                                        Pendiente 
                                      {else} 
                                        {$proveedor.dropshipping.date_expedicion|date_format:"%d-%m-%Y"} 
                                      {/if}
                                    </span>
          </div>
          <div class="col-lg-6">                
            <b>Seguimiento:</b> {if $proveedor.dropshipping.tracking == ''} 
                                  <br>Pendiente de envío 
                                {else}
                                  {$proveedor.dropshipping.tracking }
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
            {foreach from=$proveedor.productos item=producto}  
              <tr>
                <td><img src="https://{$producto.image_path}" alt="" class="imgm img-thumbnail" height="49px" width="45px"></td>
                <td>{$producto.product_name}</td>
                <td>{$producto.product_reference}</td>
                <td>{$producto.product_supplier_reference}</td>
                <td class="text-center">{$producto.product_quantity}</td>
                <td class="text-center">{$producto.variant_quantity_accepted}</td>
                <td class="text-center">{$producto.variant_result}</td>
                <td>{if !$producto.variant_msg}Sin info{else}{$producto.variant_msg}{/if}</td>
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



        