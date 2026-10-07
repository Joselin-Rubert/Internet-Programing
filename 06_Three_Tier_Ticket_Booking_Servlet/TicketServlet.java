import java.io.*;
import jakarta.servlet.*;
import jakarta.servlet.http.*;

public class TicketServlet extends HttpServlet {
 protected void doPost(HttpServletRequest req,HttpServletResponse res)throws IOException{
  String name=req.getParameter("name"), source=req.getParameter("source"), destination=req.getParameter("destination");
  int count=Integer.parseInt(req.getParameter("ticketCount"));
  res.setContentType("text/html"); PrintWriter out=res.getWriter();
  out.println("<h1>Booking Details</h1><p>Name: "+name+"</p><p>Route: "+source+" to "+destination+"</p><p>Tickets: "+count+"</p>");
 }
}