/**
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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

// window.onload = function() {
//     if (window.jQuery) {  
//         // jQuery is loaded  
//         console.log("Yeah!");
//     } else {
//         // jQuery is not loaded
//         console.log("Doesn't Work");
//     }
// }

//cambiamos el color de la línea de la tabla que tenga en su sexto td un texto conteniendo KID (referencia)
//04/12/2020 también para los que empiezan por PET
//07/11/2023 Lo quitamos ya que además de haber añadido campos a la tabla y ya no ser el sexto, muchos productos que no son de Cerdá tienen KID en la referencia y otros de Cerdá adult no lo tienen, y también están los de Karactermanía que se piden automaticamente
// $(document).ready(function(){    
//     //aparentemente no puedo cambiar el color de tr sino de los hijos de tr, así que encuentro el sexto td que tiene KID en el texto, busco el tr más cercano, y a sus hijos les cambio el color
//     $('.table td:nth-child(7):contains(KID)').closest('tr').children('td, th').css("background-color", "#ffc994");
//     $('.table td:nth-child(7):contains(PET)').closest('tr').children('td, th').css("background-color", "#ffc994");
//     // console.log('estoy en js dedeess');
// });