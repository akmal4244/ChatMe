<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DietKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->value('id');
        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Demo User',
                'email' => 'demo@chatme.akmalmarvis.com',
                'password' => Hash::make(Str::password(32)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $chatbotId = DB::table('chatbots')->value('id');
        if (! $chatbotId) {
            $chatbotId = DB::table('chatbots')->insertGetId([
                'user_id' => $userId,
                'name' => 'Diet Assistant',
                'slug' => 'diet-assistant-'.Str::random(6),
                'bot_name' => 'DietBot',
                'welcome_message' => 'Hello! Ask me anything about diet, nutrition, and weight loss!',
                'avatar_url' => 'akmal3d.png',
                'primary_color' => '#4F46E5',
                'secondary_color' => '#ffffff',
                'position' => 'bottom-right',
                'is_active' => 1,
                'api_key' => 'cm_'.Str::random(32),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $items = [
            ['What is a calorie deficit?', 'A calorie deficit is when you consume fewer calories than your body burns. This is the fundamental principle behind weight loss. To lose 1 kg of fat, you need a deficit of approximately 7,700 calories. A safe deficit is 300-500 calories per day, leading to 0.5-1 kg weight loss per week.', 'Basics', 'calorie,deficit,weight loss'],
            ['How many calories should I eat per day?', 'Daily calorie needs depend on age, gender, weight, height, and activity level. Average: Women need 1,600-2,400 calories/day, Men need 2,000-3,000 calories/day. For weight loss, reduce by 300-500 calories from maintenance.', 'Basics', 'calorie,daily,TDEE'],
            ['What are macronutrients?', 'Macronutrients are the three main nutrients: 1) Protein (4 cal/g) builds muscle. 2) Carbohydrates (4 cal/g) provide energy. 3) Fats (9 cal/g) support hormone production. A balanced diet: 40-50% carbs, 25-35% protein, 20-30% fats.', 'Nutrition', 'macro,protein,carbs,fat'],
            ['What is the best diet for weight loss?', 'There is no single best diet. Evidence-based approaches: Mediterranean diet, DASH diet, flexible dieting (IIFYM). Key: whole foods, adequate protein, vegetables, hydration.', 'Weight Loss', 'best diet,mediterranean'],
            ['How much protein do I need?', 'General health: 0.8g per kg body weight. Weight loss: 1.6-2.2g per kg. For a 70kg person losing weight: 112-154g daily. Protein preserves muscle and increases satiety.', 'Nutrition', 'protein,intake,muscle'],
            ['What foods are high in protein?', 'Chicken breast (31g/100g), Tuna (30g/100g), Lean beef (26g/100g), Tofu (8g/100g), Eggs (6g each), Greek yogurt (10g/100g), Lentils (9g/100g), Salmon (25g/100g).', 'Nutrition', 'protein,food,sources'],
            ['Is intermittent fasting effective?', 'Yes. Common methods: 16:8 (fast 16h, eat 8h), 5:2. Works by reducing overall calorie intake. Benefits: improved insulin sensitivity, autophagy.', 'Diet Methods', 'intermittent fasting,IF,16:8'],
            ['How can I reduce belly fat?', 'You cannot spot-reduce fat. Requires: 1) Calorie deficit, 2) Exercise (cardio + strength), 3) Stress management, 4) 7-9h sleep, 5) Limit alcohol and sugar.', 'Weight Loss', 'belly fat,visceral,stomach'],
            ['What should I eat before a workout?', 'Pre-workout (1-3h before): Carbs + moderate protein. Examples: banana + Greek yogurt, oatmeal + eggs. Under 1h: light carbs like banana.', 'Nutrition', 'pre workout,energy,carbs'],
            ['Is breakfast important for weight loss?', 'Not mandatory. If not hungry, skip. Total daily calories matter most, not timing.', 'Weight Loss', 'breakfast,skip,meal timing'],
            ['How much water should I drink?', '2-3 liters (8-12 cups) daily. Drink 500ml before meals to reduce intake ~13%. Pale yellow urine = good hydration.', 'Basics', 'water,hydration,fluid'],
            ['What are the best exercises for weight loss?', 'Combine: Strength 2-3x/week, Cardio 150-300 min/week, HIIT 1-2x/week, Daily movement. NEAT = 15-30% of calorie burn.', 'Exercise', 'exercise,cardio,strength,HIIT'],
            ['How fast can I lose weight safely?', '0.5-1 kg (1-2 lbs) per week. Faster = muscle loss, deficiencies. Initial loss is water weight. Avoid crash diets.', 'Weight Loss', 'safe rate,weekly,pace'],
            ['What is BMI?', 'BMI = weight(kg) / height(m)^2. <18.5 underweight, 18.5-24.9 normal, 25-29.9 overweight, 30+ obese. Does NOT distinguish muscle from fat.', 'Basics', 'BMI,body mass,measure'],
            ['How can I stop emotional eating?', '1) Identify triggers, 2) Mindful eating, 3) Alternative coping (walk, journal), 4) Remove trigger foods, 5) Wait 10 min.', 'Behavior', 'emotional eating,stress,binge'],
            ['What is a balanced meal plate?', 'Harvard Plate: 1/2 vegetables/fruits, 1/4 whole grains, 1/4 protein. Add healthy fats + water.', 'Nutrition', 'balanced,plate,portion'],
            ['Are carbs bad for weight loss?', 'No. Complex carbs (whole grains, vegetables) = good. Simple carbs (sugar) = limit. Total calories matter most.', 'Nutrition', 'carbs,good carbs,bad carbs'],
            ['How do I maintain weight after losing?', 'Track periodically, keep protein high, stay active, weekly weigh-ins, good sleep.', 'Weight Loss', 'maintenance,keep off'],
            ['What are healthy snacks?', 'Apple + PB (150 cal), Greek yogurt + berries (120 cal), Almonds (160 cal), Carrots + hummus (130 cal), Egg (70 cal).', 'Nutrition', 'snacks,healthy,low calorie'],
            ['What is metabolism?', 'Converting food to energy. BMR (60-75%), TEF (10%), Activity (15-30%). Boost: muscle, protein, hydration, sleep.', 'Basics', 'metabolism,BMR,burn'],
            ['How important is fiber?', 'Very! Increases satiety, slows digestion, feeds gut bacteria. Aim 25-30g daily.', 'Nutrition', 'fiber,satiety,digestion'],
            ['What is the Mediterranean diet?', 'Vegetables, fruits, whole grains, legumes, nuts, olive oil, fish 2x/week. Benefits: heart health, longevity.', 'Diet Methods', 'mediterranean,olive oil,fish'],
            ['How do I deal with food cravings?', 'Wait 15-20 min, drink water, eat protein, sleep enough, remove trigger foods, allow treats (80/20).', 'Behavior', 'craving,urge,control'],
            ['Apa itu defisit kalori?', 'Defisit kalori berlaku apabila anda mengambil kurang kalori daripada yang dibakar. Untuk kurangkan 1 kg lemak, perlu defisit ~7,700 kalori. Defisit selamat: 300-500 kalori sehari.', 'Asas', 'kalori,defisit,turun berat'],
            ['Berapa kalori perlu saya makan?', 'Wanita: 1,600-2,400 kalori/hari. Lelaki: 2,000-3,000 kalori/hari. Untuk turun berat, kurangkan 300-500 kalori.', 'Asas', 'kalori,harian,TDEE'],
            ['Bagaimana cara mengurangkan lemak perut?', 'Tidak boleh spot-reduce. Perlu: defisit kalori, senaman tetap, urus stres, tidur 7-9 jam, hadkan alkohol dan gula.', 'Turun Berat', 'lemak perut,perut,buncit'],
            ['Berapa banyak air perlu diminum?', '2-3 liter (8-12 gelas) sehari. Minum 500ml sebelum makan boleh kurangkan pengambilan kalori ~13%.', 'Asas', 'air,hidrasi,minum'],
            ['Apakah senaman terbaik untuk turun berat?', 'Kombinasi: latihan kekuatan 2-3x/minggu, kardio 150-300 min/minggu, HIIT 1-2x/minggu, pergerakan harian.', 'Senaman', 'senaman,kardio,kekuatan'],
            ['Bagaimana nak kawal nafsu makan?', '1) Tunggu 15-20 minit, 2) Minum air, 3) Makan protein tinggi, 4) Tidur cukup, 5) Jauhkan makanan pencetus.', 'Tingkah Laku', 'nafsu,mengidam,kawal'],
            ['Adakah sarapan penting?', 'Tidak wajib. Jika tidak lapar pagi, boleh skip. Yang penting jumlah kalori harian dan kualiti makanan.', 'Turun Berat', 'sarapan,skip,pagi'],
            ['Apa itu makronutrien?', 'Makronutrien adalah tiga nutrien utama: Protein (4 kal/g) membina otot, Karbohidrat (4 kal/g) sumber tenaga, Lemak (9 kal/g) untuk hormon.', 'Pemakanan', 'makro,protein,karbohidrat,lemak'],
            ['Makanan apa tinggi protein?', 'Dada ayam (31g/100g), Tuna (30g/100g), Daging tanpa lemak (26g/100g), Tauhu (8g/100g), Telur (6g), Yogurt Greek (10g/100g).', 'Pemakanan', 'protein,makanan,sumber'],
            ['Apakah diet terbaik untuk turun berat?', 'Tiada satu diet terbaik. Diet paling berkesan adalah yang boleh dikekalkan. Mediterranean, DASH, atau diet seimbang kalori-terhad.', 'Turun Berat', 'diet terbaik,mediterranean'],
            ['Berapa banyak protein yang saya perlukan?', 'Kesihatan umum: 0.8g per kg. Turun berat: 1.6-2.2g per kg. Untuk 70kg: 112-154g sehari.', 'Pemakanan', 'protein,pengambilan,otot'],
            ['Apa itu diet keto?', 'Diet sangat rendah karbohidrat, tinggi lemak (5-10% karbo, 70-75% lemak). Badan masuk ketosis, bakar lemak untuk tenaga.', 'Kaedah Diet', 'keto,ketogenik,rendah karbohidrat'],
            ['Apa snek sihat untuk turun berat?', 'Epal + mentega kacang (150 kal), Yogurt Greek + beri (120 kal), Badam (160 kal), Lobak + hummus (130 kal).', 'Pemakanan', 'snek,sihat,rendah kalori'],
            ['Bagaimana nak kekalkan berat selepas turun?', 'Pantau berkala, kekalkan protein tinggi, kekal aktif, timbang setiap minggu, jaga tidur.', 'Turun Berat', 'kekalkan,penyelenggaraan'],
        ];

        foreach ($items as $item) {
            DB::table('knowledge_items')->insert([
                'chatbot_id' => $chatbotId,
                'question' => $item[0],
                'answer' => $item[1],
                'category' => $item[2],
                'tags' => $item[3],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
