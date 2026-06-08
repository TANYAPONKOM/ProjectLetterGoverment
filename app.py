from flask import Flask, render_template, request, jsonify
from thaispellcheck import check   # ดึงฟังก์ชัน check() ที่มีอยู่แล้ว

app = Flask(__name__)

@app.route("/")
def index():
    return render_template("index.html")

@app.route("/check", methods=["POST"])
def check_spelling():
    text = request.json.get("text", "")

    # highlight คำผิด (autocorrect=False)
    highlighted = check(text, autocorrect=False)

    # ข้อความที่แก้ไขอัตโนมัติ (autocorrect=True)
    corrected = check(text, autocorrect=True)

    return jsonify({
        "input": text,
        "highlighted": highlighted,
        "corrected": corrected
    })

if __name__ == "__main__":
    app.run(debug=True)
