import java.io.*;
import jakarta.servlet.*;
import jakarta.servlet.http.*;

public class ExamServlet extends HttpServlet {
    protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException {
        int score=0;
        if("Hyper Text Markup Language".equals(req.getParameter("q1"))) score++;
        if("Styling".equals(req.getParameter("q2"))) score++;
        if("JavaScript".equalsIgnoreCase(req.getParameter("q3"))) score++;
        res.setContentType("text/html");
        PrintWriter out=res.getWriter();
        out.println("<h1>Exam Result</h1><p>Your Score: "+score+"/3</p>");
    }
}