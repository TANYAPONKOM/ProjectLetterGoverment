from pathlib import Path
from typing import List
import json
import re

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from pythainlp.spell import spell
from pythainlp.tokenize import word_tokenize

BASE_DIR = Path(__file__).resolve().parent
CUSTOM_DICT_PATH = BASE_DIR / "custom_dict.txt"
MISSPELL_PATH = BASE_DIR / "common_misspellings.json"


def load_misspellings() -> dict:
    if not MISSPELL_PATH.exists():
        return {}

    with open(MISSPELL_PATH, "r", encoding="utf-8") as f:
        return json.load(f)


def load_custom_dict() -> set[str]:
    if not CUSTOM_DICT_PATH.exists():
        return set()

    words = set()
    with open(CUSTOM_DICT_PATH, "r", encoding="utf-8") as f:
        for line in f:
            word = line.strip()
            if word:
                words.add(word)
    return words


COMMON_MISSPELLINGS = load_misspellings()
CUSTOM_WORDS = load_custom_dict()

app = FastAPI(title="Thai Spell Check API")

# สำหรับ Render + InfinityFree:
# ตอนทดสอบให้เปิด allow_origins=["*"] ก่อน เพื่อไม่ให้ติด CORS
# ถ้าระบบขึ้นสมบูรณ์แล้ว ค่อยเปลี่ยนเป็นโดเมนจริงของเว็บคุณเพื่อความปลอดภัย
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


class SpellCheckRequest(BaseModel):
    # ทำให้ field ไม่บังคับ เผื่อ frontend บางไฟล์ส่งมาแค่ {"text": "..."}
    field: str = ""
    text: str = ""


def is_thai_word(word: str) -> bool:
    return bool(re.search(r"[ก-๙]", word))


def should_ignore_word(word: str) -> bool:
    if not word:
        return True

    word = word.strip()

    if not word:
        return True

    # ข้ามตัวเลข
    if re.fullmatch(r"[0-9]+([.,][0-9]+)?", word):
        return True

    # ข้ามวันที่/รหัส/ทะเบียน/คำอังกฤษล้วน
    if re.fullmatch(r"[A-Za-z0-9\-/_.]+", word):
        return True

    # ข้ามคำสั้นมาก
    if len(word) <= 1:
        return True

    # ข้ามถ้าเป็นคำใน custom dictionary
    if word in CUSTOM_WORDS:
        return True

    # ข้ามถ้าไม่มีตัวอักษรไทย
    if not is_thai_word(word):
        return True

    return False


def tokenize_text(text: str) -> List[str]:
    return word_tokenize(text, engine="newmm")


def check_word(word: str):
    if should_ignore_word(word):
        return None

    if word in COMMON_MISSPELLINGS:
        return {
            "word": word,
            "suggestions": COMMON_MISSPELLINGS[word][:5],
        }

    suggestions = spell(word)

    if not suggestions:
        return None

    if suggestions[0] == word:
        return None

    cleaned_suggestions = []
    for suggestion in suggestions:
        suggestion = suggestion.strip()
        if suggestion and suggestion != word and suggestion not in cleaned_suggestions:
            cleaned_suggestions.append(suggestion)

    if not cleaned_suggestions:
        return None

    return {
        "word": word,
        "suggestions": cleaned_suggestions[:5],
    }


@app.get("/")
def root():
    return {"message": "Thai Spell Check API is running"}


@app.get("/health")
def health_check():
    return {"status": "ok"}


@app.post("/api/spell-check")
def api_spell_check(payload: SpellCheckRequest):
    text = payload.text.strip()

    if not text:
        return {
            "checked": True,
            "hasError": False,
            "errors": [],
        }

    found_errors = []
    seen_words = set()

    # 1) เช็กจาก common_misspellings.json ก่อน
    # เรียงจากคำยาวไปคำสั้น เพื่อลดปัญหาจับคำผิดที่เป็นแค่ส่วนหนึ่งของคำยาว
    sorted_misspellings = sorted(
        COMMON_MISSPELLINGS.items(),
        key=lambda item: len(item[0]),
        reverse=True,
    )

    for wrong_word, suggestions in sorted_misspellings:
        if wrong_word in seen_words:
            continue

        if should_ignore_word(wrong_word):
            continue

        if wrong_word in CUSTOM_WORDS:
            continue

        if wrong_word in text:
            is_part_of_correct_suggestion = any(
                wrong_word != correct_word
                and wrong_word in correct_word
                and correct_word in text
                for correct_word in suggestions
            )

            if is_part_of_correct_suggestion:
                continue

            found_errors.append({
                "wrongWord": wrong_word,
                "suggestions": suggestions[:5],
            })
            seen_words.add(wrong_word)

    # 2) tokenize แล้วเช็กทีละคำด้วย PyThaiNLP
    tokens = tokenize_text(text)

    for token in tokens:
        if token in seen_words:
            continue

        result = check_word(token)
        if result:
            found_errors.append({
                "wrongWord": result["word"],
                "suggestions": result["suggestions"],
            })
            seen_words.add(result["word"])

    return {
        "checked": True,
        "hasError": len(found_errors) > 0,
        "errors": found_errors,
    }


# คำสั่งรันบนเครื่องคุณ:
# python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
#
# Start Command บน Render:
# uvicorn main:app --host 0.0.0.0 --port $PORT
