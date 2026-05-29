import express from "express";
import path from "path";
import nodemailer from "nodemailer";
import { createServer as createViteServer } from "vite";

async function sendNotificationEmail(lead: {
  name: string;
  whatsapp: string;
  goal: string;
  score?: number;
  discount?: number;
  promoCode?: string;
}) {
  const recipient = process.env.NOTIFY_EMAIL || "theenglishsparrow@gmail.com";
  const user = process.env.SMTP_USER;
  const pass = process.env.SMTP_PASS;
  const host = process.env.SMTP_HOST || "smtp.gmail.com";
  const port = parseInt(process.env.SMTP_PORT || "587");

  console.log(`[Email Service] Attempting to notify ${recipient}`);
  
  if (!user || !pass) {
    console.warn(
      `[Email Service] SMTP credentials (SMTP_USER / SMTP_PASS) are not configured.`,
      `The server would send the following lead to ${recipient}:`,
      lead
    );
    return {
      success: false,
      warning: "SMTP credentials not configured. Read current logs for details."
    };
  }

  try {
    const transporter = nodemailer.createTransport({
      host,
      port,
      secure: port === 465, // true for 465, false for 587/other
      auth: {
        user,
        pass,
      },
    });

    const isScore = (lead.score !== undefined && lead.score > 0);
    const textContent = `
Новая заявка на консультацию от Global Sparrow!

👤 Имя клиента: ${lead.name}
📱 WhatsApp: ${lead.whatsapp}
🎯 Главная цель: ${lead.goal}
${isScore ? `🎮 Результат в игре: ${lead.score} очков` : ""}
${isScore ? `🎁 Скидка за игру: ${lead.discount}%` : ""}
${isScore ? `🎟️ Промокод: ${lead.promoCode}` : ""}
📅 Время заявки: ${new Date().toLocaleString("ru-RU")}
    `.trim();

    const htmlContent = `
      <div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="background: linear-gradient(135deg, #ef4444 0%, #0d9488 50%, #d97706 100%); padding: 24px; text-align: center; color: white;">
          <h1 style="margin: 0; font-size: 24px; font-weight: 800; font-family: sans-serif;">GLOBAL SPARROW</h1>
          <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">Новая заявка на консультацию!</p>
        </div>
        <div style="padding: 24px; background-color: #ffffff; color: #1e293b; line-height: 1.6;">
          <p style="font-size: 16px; margin-top: 0; margin-bottom: 20px;">Здравствуйте! На сайте Global Sparrow была заполнена новая форма бронирования консультации.</p>
          
          <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; width: 35%; color: #64748b; font-size: 14px; text-transform: uppercase;">Имя:</td>
              <td style="padding: 10px 0; font-size: 15px; font-weight: 600; color: #0f172a;">${lead.name}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; text-transform: uppercase;">WhatsApp:</td>
              <td style="padding: 10px 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                <a href="https://wa.me/${lead.whatsapp.replace(/\D/g, "")}" style="color: #0d9488; text-decoration: none;">${lead.whatsapp}</a>
              </td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; text-transform: uppercase;">Цель обучения:</td>
              <td style="padding: 10px 0; font-size: 15px; color: #334155;">${lead.goal}</td>
            </tr>
            ${isScore ? `
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; text-transform: uppercase;">Счет в игре:</td>
              <td style="padding: 10px 0; font-size: 15px; color: #0f172a; font-weight: bold;">${lead.score} очков</td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; text-transform: uppercase;">Скидка:</td>
              <td style="padding: 10px 0; font-size: 16px; color: #0f172a; font-weight: bold;">
                <span style="background-color: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 9999px; border: 1px solid #bbf7d0;">${lead.discount}%</span>
              </td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px 0; font-weight: bold; color: #64748b; font-size: 14px; text-transform: uppercase;">Промокод:</td>
              <td style="padding: 10px 0; font-size: 15px; font-family: monospace; font-weight: bold; color: #0d9488; letter-spacing: 0.05em;">${lead.promoCode}</td>
            </tr>
            ` : ""}
          </table>

          <div style="text-align: center; margin-top: 30px;">
            <a href="https://wa.me/${lead.whatsapp.replace(/\D/g, "")}" style="display: inline-block; background-color: #10b981; color: white; padding: 12px 24px; font-weight: bold; text-decoration: none; border-radius: 12px; font-size: 14px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);">Открыть диалог в WhatsApp</a>
          </div>
        </div>
        <div style="background-color: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
          <p style="margin: 0;">Это автоматическое письмо от лид-формы Global Sparrow.</p>
        </div>
      </div>
    `;

    const info = await transporter.sendMail({
      from: `"Global Sparrow Leads" <${user}>`,
      to: recipient,
      subject: `🔥 Новая заявка: ${lead.name} (${lead.goal})`,
      text: textContent,
      html: htmlContent,
    });

    console.log(`[Email Service] Success! Message sent: ${info.messageId}`);
    return { success: true, messageId: info.messageId };
  } catch (error: any) {
    const errorStr = error?.message || String(error);
    console.error("[Email Service] Failed to send email via SMTP:", error);
    
    if (errorStr.includes("Application-specific password required") || errorStr.includes("534-5.7.9")) {
      console.error(
        "\n========================================================================",
        "\n[HELP] GMAIL SMTP CONFIGURATION ERROR:",
        "\nYour Gmail account has 2-Step Verification enabled, which requires a custom App Password.",
        "\nTo fix this:",
        "\n1. Go to: https://myaccount.google.com/",
        "\n2. Choose 'Security' on the left side menu.",
        "\n3. Scroll down to 'How you sign in to Google' & make sure '2-Step Verification' is turned ON.",
        "\n4. Select 'App passwords' (if not visible, search for 'App passwords' in the search bar).",
        "\n5. Select 'Mail' and 'Other (Custom name)' (e.g., Global Sparrow Lead Mailer).",
        "\n6. Copy the generated 16-character password (without spaces).",
        "\n7. Replace your standard password with this 16-character App Password in the Secrets panel under SMTP_PASS.",
        "\n========================================================================\n"
      );
    }
    
    return { 
      success: false, 
      error: errorStr, 
      tip: errorStr.includes("Application-specific password required") || errorStr.includes("534-5.7.9")
        ? "Please use a Google App Password instead of your primary Gmail password. Check server.ts logs for instructions."
        : undefined
    };
  }
}

async function startServer() {
  const app = express();
  const PORT = 3000;

  app.use(express.json());

  // API Route to submit lead and send email
  app.post("/api/lead", async (req, res) => {
    try {
      const { name, whatsapp, goal, score, discount, promoCode } = req.body;
      
      if (!name || !whatsapp) {
        return res.status(400).json({ error: "Name and WhatsApp are required fields." });
      }

      const emailResponse = await sendNotificationEmail({
        name,
        whatsapp,
        goal,
        score,
        discount,
        promoCode
      });

      return res.status(200).json({
        success: true,
        message: "Lead processed successfully",
        email: emailResponse
      });
    } catch (err: any) {
      console.error("API Error in /api/lead:", err);
      return res.status(500).json({ error: "Failed to process lead request." });
    }
  });

  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Express server running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
