function loadProducts(){
 const xhr=new XMLHttpRequest();
 xhr.onreadystatechange=function(){
  if(this.readyState===4 && this.status===200){
   const xml=this.responseXML, items=xml.getElementsByTagName("product");
   let html="<h2>Products</h2><ul>";
   for(let i=0;i<items.length;i++) html+="<li>"+items[i].getElementsByTagName("name")[0].textContent+" - ₹"+items[i].getElementsByTagName("price")[0].textContent+"</li>";
   document.getElementById("output").innerHTML=html+"</ul>";
  }
 };
 xhr.open("GET","products.xml",true); xhr.send();
}