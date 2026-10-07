<%@ page import="java.sql.*" %>
<%
String customer=request.getParameter("customer"), product=request.getParameter("product");
int quantity=Integer.parseInt(request.getParameter("quantity"));
String url="jdbc:mysql://localhost:3306/shopdb", user="root", password="";
try {
 Class.forName("com.mysql.cj.jdbc.Driver");
 Connection con=DriverManager.getConnection(url,user,password);
 PreparedStatement ps=con.prepareStatement("INSERT INTO orders(customer,product,quantity) VALUES(?,?,?)");
 ps.setString(1,customer); ps.setString(2,product); ps.setInt(3,quantity); ps.executeUpdate();
 out.println("<h1>Order Stored Successfully</h1><p>"+customer+" ordered "+quantity+" "+product+"</p>");
 con.close();
} catch(Exception e){ out.println("<h1>Database Error</h1><p>"+e.getMessage()+"</p>"); }
%>