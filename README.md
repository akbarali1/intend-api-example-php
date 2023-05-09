# intend-api-example-php
Intend.uz ning example so'rovlari

Foydalanuvchilarni ro'ygandan o`tish sahifasiga o`tkazish uchun. Ro`ygatdan muvafaqiyatli o`tgan foydalanuvchilar ko`rsatilgan urlga yo`naltirib yuboriladi hech qanday malumotlarsiz
```
public function registration()
{
    return redirect('https://reg.intend.uz/login?back_uri='.route('web.home').'&l='.app()->getLocale());
}
```
